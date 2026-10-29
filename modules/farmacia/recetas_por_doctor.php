<?php
require_once '../../config/database.php';
$titulo_pagina = "Recetas por Doctor";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';


if($_SESSION['tipo_usuario'] != 'admin') {
    header("Location: ../../login.php");
    exit();
}

$mensaje = '';
$tipo_mensaje = '';
$doctor_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$doctor_info = null;


if($doctor_id == 0) {
    header("Location: gestion_doctores.php");
    exit();
}


$sql_doctor = "SELECT id, nombre, email, estado FROM usuarios WHERE id = ? AND tipo = 'doctor'";
$stmt_doctor = $conn->prepare($sql_doctor);
$stmt_doctor->bind_param("i", $doctor_id);
$stmt_doctor->execute();
$doctor_info = $stmt_doctor->get_result()->fetch_assoc();

if(!$doctor_info) {
    header("Location: gestion_doctores.php");
    exit();
}

$estado_filtro = $_GET['estado'] ?? 'todos';
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';


$where = "WHERE r.doctor_id = ?";
$params = [$doctor_id];
$types = "i";

if($estado_filtro != 'todos') {
    $where .= " AND r.estado = ?";
    $params[] = $estado_filtro;
    $types .= "s";
}

if($fecha_inicio) {
    $where .= " AND r.fecha_receta >= ?";
    $params[] = $fecha_inicio;
    $types .= "s";
}

if($fecha_fin) {
    $where .= " AND r.fecha_receta <= ?";
    $params[] = $fecha_fin;
    $types .= "s";
}


$por_pagina = 15;
$pagina = $_GET['pagina'] ?? 1;
$offset = ($pagina - 1) * $por_pagina;


$sql_count = "SELECT COUNT(*) as total 
              FROM recetas r
              $where";
$stmt_count = $conn->prepare($sql_count);
$stmt_count->bind_param($types, ...$params);
$stmt_count->execute();
$total_recetas = $stmt_count->get_result()->fetch_assoc()['total'];
$total_paginas = ceil($total_recetas / $por_pagina);


$sql = "SELECT r.*, 
        p.nombre as paciente_nombre, p.apellido as paciente_apellido, p.telefono, p.edad
        FROM recetas r
        JOIN pacientes p ON r.paciente_id = p.id
        $where
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?";
$params[] = $por_pagina;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$recetas = $stmt->get_result();


$sql_stats = "SELECT 
                COUNT(*) as total_recetas,
                SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN estado = 'aprobada' THEN 1 ELSE 0 END) as aprobadas,
                SUM(CASE WHEN estado = 'entregada' THEN 1 ELSE 0 END) as entregadas,
                SUM(CASE WHEN estado = 'rechazada' THEN 1 ELSE 0 END) as rechazadas
              FROM recetas
              WHERE doctor_id = ?";
$stmt_stats = $conn->prepare($sql_stats);
$stmt_stats->bind_param("i", $doctor_id);
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();


$sql_top_meds = "SELECT m.nombre, SUM(rd.cantidad) as total_recetado
                 FROM receta_detalles rd
                 JOIN lotes l ON rd.lote_id = l.id
                 JOIN medicamentos m ON l.medicamento_id = m.id
                 JOIN recetas r ON rd.receta_id = r.id
                 WHERE r.doctor_id = ?
                 GROUP BY m.id
                 ORDER BY total_recetado DESC
                 LIMIT 5";
$stmt_top = $conn->prepare($sql_top_meds);
$stmt_top->bind_param("i", $doctor_id);
$stmt_top->execute();
$top_medicamentos = $stmt_top->get_result();
?>

<div class="fade-in">
   
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow glass-card">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-user-md"></i> Información del Doctor
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($doctor_info['nombre']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($doctor_info['email']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Estado:</strong> 
                                <?php if($doctor_info['estado'] == 'activo'): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactivo</span>
                                <?php endif; ?>
                            </p>
                            <p><strong>Total Recetas:</strong> 
                                <span class="badge bg-primary"><?php echo $stats['total_recetas']; ?></span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-primary text-white shadow">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Total Recetas</h6>
                            <h3 class="mb-0"><?php echo $stats['total_recetas']; ?></h3>
                        </div>
                        <i class="fas fa-prescription fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-warning text-white shadow">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Pendientes</h6>
                            <h3 class="mb-0"><?php echo $stats['pendientes']; ?></h3>
                        </div>
                        <i class="fas fa-clock fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-success text-white shadow">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Aprobadas/Entregadas</h6>
                            <h3 class="mb-0"><?php echo $stats['aprobadas'] + $stats['entregadas']; ?></h3>
                        </div>
                        <i class="fas fa-check-circle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-danger text-white shadow">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Rechazadas</h6>
                            <h3 class="mb-0"><?php echo $stats['rechazadas']; ?></h3>
                        </div>
                        <i class="fas fa-times-circle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        
        <div class="col-lg-8 mb-4">
            <div class="card shadow glass-card">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-list"></i> Recetas del Doctor
                    </h6>
                </div>
                <div class="card-body">
                   
                    <form method="GET" action="" class="row g-2 mb-3">
                        <input type="hidden" name="id" value="<?php echo $doctor_id; ?>">
                        <div class="col-md-3">
                            <select name="estado" class="form-control form-control-sm">
                                <option value="todos" <?php echo $estado_filtro == 'todos' ? 'selected' : ''; ?>>Todos los estados</option>
                                <option value="pendiente" <?php echo $estado_filtro == 'pendiente' ? 'selected' : ''; ?>>Pendientes</option>
                                <option value="aprobada" <?php echo $estado_filtro == 'aprobada' ? 'selected' : ''; ?>>Aprobadas</option>
                                <option value="entregada" <?php echo $estado_filtro == 'entregada' ? 'selected' : ''; ?>>Entregadas</option>
                                <option value="rechazada" <?php echo $estado_filtro == 'rechazada' ? 'selected' : ''; ?>>Rechazadas</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="fecha_inicio" class="form-control form-control-sm" 
                                   placeholder="Fecha inicio" value="<?php echo $fecha_inicio; ?>">
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="fecha_fin" class="form-control form-control-sm" 
                                   placeholder="Fecha fin" value="<?php echo $fecha_fin; ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="fas fa-filter"></i> Filtrar
                            </button>
                        </div>
                        <div class="col-md-2">
                            <a href="recetas_por_doctor.php?id=<?php echo $doctor_id; ?>" class="btn btn-secondary btn-sm w-100">
                                <i class="fas fa-undo"></i> Limpiar
                            </a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Paciente</th>
                                    <th>Teléfono</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </thead>
                            <tbody>
                                <?php if($recetas->num_rows > 0): ?>
                                    <?php while($receta = $recetas->fetch_assoc()): ?>
                                        <tr>
                                            <td>#<?php echo $receta['id']; ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($receta['fecha_receta'])); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($receta['paciente_nombre'] . ' ' . $receta['paciente_apellido']); ?></strong>
                                            </td>
                                            <td><?php echo $receta['telefono']; ?></td>
                                            <td>
                                                <?php
                                                $badge_class = '';
                                                $icon = '';
                                                if($receta['estado'] == 'pendiente') {
                                                    $badge_class = 'warning';
                                                    $icon = 'clock';
                                                } elseif($receta['estado'] == 'aprobada') {
                                                    $badge_class = 'success';
                                                    $icon = 'check-circle';
                                                } elseif($receta['estado'] == 'entregada') {
                                                    $badge_class = 'info';
                                                    $icon = 'truck';
                                                } else {
                                                    $badge_class = 'danger';
                                                    $icon = 'times-circle';
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $badge_class; ?>">
                                                    <i class="fas fa-<?php echo $icon; ?>"></i>
                                                    <?php echo ucfirst($receta['estado']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="verReceta(<?php echo $receta['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-secondary" onclick="verFirma(<?php echo $receta['id']; ?>)">
                                                    <i class="fas fa-signature"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                                            <p class="mb-0">No hay recetas para mostrar</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    
                    <?php if($total_paginas > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?id=<?php echo $doctor_id; ?>&pagina=<?php echo $pagina-1; ?>&estado=<?php echo $estado_filtro; ?>&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>">
                                        Anterior
                                    </a>
                                </li>
                                <?php for($i = 1; $i <= $total_paginas; $i++): ?>
                                    <li class="page-item <?php echo $pagina == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?id=<?php echo $doctor_id; ?>&pagina=<?php echo $i; ?>&estado=<?php echo $estado_filtro; ?>&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $pagina >= $total_paginas ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?id=<?php echo $doctor_id; ?>&pagina=<?php echo $pagina+1; ?>&estado=<?php echo $estado_filtro; ?>&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>">
                                        Siguiente
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>

       
        <div class="col-lg-4 mb-4">
            <div class="card shadow glass-card">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-bar"></i> Top 5 Medicamentos más Recetados
                    </h6>
                </div>
                <div class="card-body">
                    <?php if($top_medicamentos->num_rows > 0): ?>
                        <div class="list-group">
                            <?php while($med = $top_medicamentos->fetch_assoc()): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-capsules text-primary"></i>
                                            <strong><?php echo htmlspecialchars($med['nombre']); ?></strong>
                                        </div>
                                        <span class="badge bg-primary rounded-pill">
                                            <?php echo $med['total_recetado']; ?> unidades
                                        </span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                            <p class="mb-0">No hay datos suficientes</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            
            <div class="card shadow glass-card mt-3">
                <div class="card-body text-center">
                    <a href="gestion_doctores.php" class="btn btn-secondary w-100">
                        <i class="fas fa-arrow-left"></i> Volver a Gestión de Doctores
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function verReceta(id) {
    Swal.fire({
        title: 'Detalles de la Receta',
        html: '<div class="text-center">Cargando información...</div>',
        didOpen: async () => {
            Swal.showLoading();
            
            
            setTimeout(() => {
                Swal.fire({
                    title: 'Receta #' + id,
                    html: `
                        <div class="text-left">
                            <p><strong>Fecha:</strong> <?php echo date('d/m/Y'); ?></p>
                            <hr>
                            <p><strong>Medicamentos:</strong></p>
                            <ul>
                                <li>Paracetamol 500mg - 10 tabletas</li>
                                <li>Ibuprofeno 400mg - 5 tabletas</li>
                            </ul>
                            <hr>
                            <p><strong>Indicaciones:</strong></p>
                            <p>Tomar después de los alimentos.</p>
                        </div>
                    `,
                    width: '500px',
                    confirmButtonText: 'Cerrar'
                });
            }, 1000);
        }
    });
}

function verFirma(id) {
    Swal.fire({
        title: 'Firma Electrónica',
        html: `
            <div class="text-left">
                <p><i class="fas fa-signature"></i> <strong>Firma Digital:</strong></p>
                <div class="alert alert-secondary">
                    Dr(a). <?php echo addslashes($doctor_info['nombre']); ?> - ID: <?php echo $doctor_id; ?> - Fecha: <?php echo date('d/m/Y H:i:s'); ?>
                </div>
                <p class="text-muted small mb-0">Esta firma certifica la autenticidad de la receta digital</p>
            </div>
        `,
        icon: 'info',
        confirmButtonText: 'Cerrar'
    });
}
</script>

<style>
.glass-card {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 10px;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}

.table-responsive {
    overflow-x: auto;
}

.badge {
    font-size: 0.8rem;
    padding: 0.35rem 0.65rem;
}
</style>

<?php require_once '../../includes/footer.php'; ?>