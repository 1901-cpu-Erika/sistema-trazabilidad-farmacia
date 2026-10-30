<?php
if (session_status() === PHP_SESSION_NONE) {
    session_save_path('/tmp');
    session_start();
}
// ... resto de tu código del header
require_once '../../config/database.php';
$titulo_pagina = "Panel de Control - Farmacia";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$stats = [];

// 1. Recetas Pendientes
$sql = "SELECT COUNT(*) as total FROM recetas WHERE estado = 'pendiente'";
$result = $conn->query($sql);
$stats['recetas_pendientes'] = $result->fetch_assoc()['total'];

// 2. Stock Bajo (Contando cuántos lotes tienen menos de 10)
$sql = "SELECT COUNT(*) as total FROM lotes WHERE cantidad < 10";
$result = $conn->query($sql);
$stats['stock_bajo'] = $result->fetch_assoc()['total'] ?? 0;

// 3. Por Vencer (30 días)
$sql = "SELECT COUNT(*) as total FROM lotes WHERE fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
$result = $conn->query($sql);
$stats['por_vencer'] = $result->fetch_assoc()['total'];

// 4. Pacientes Activos
$sql = "SELECT COUNT(*) as total FROM pacientes";
$result = $conn->query($sql);
$stats['total_pacientes'] = $result->fetch_assoc()['total'];

// PAGINACIÓN DE RECETAS RECIENTES (6 por página)
$por_pagina = 6;
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
if ($pagina < 1) $pagina = 1;
$offset = ($pagina - 1) * $por_pagina;

$sql_count_recetas = "SELECT COUNT(*) as total FROM recetas";
$total_recetas = $conn->query($sql_count_recetas)->fetch_assoc()['total'];
$total_paginas = ceil($total_recetas / $por_pagina);

$sql = "SELECT r.*, p.nombre as paciente_nombre, p.apellido as paciente_apellido, 
               u.nombre as doctor_nombre
        FROM recetas r
        JOIN pacientes p ON r.paciente_id = p.id
        JOIN usuarios u ON r.doctor_id = u.id
        ORDER BY r.created_at DESC
        LIMIT $por_pagina OFFSET $offset";
$recetas_recientes = $conn->query($sql);

// CONSULTAS PARA LAS VENTANAS MODALES
$lista_stock_bajo = $conn->query("SELECT m.nombre, l.cantidad, l.fecha_vencimiento FROM lotes l JOIN medicamentos m ON l.medicamento_id = m.id WHERE l.cantidad < 10 ORDER BY l.cantidad ASC");
$lista_por_vencer = $conn->query("SELECT m.nombre, l.cantidad, l.fecha_vencimiento FROM lotes l JOIN medicamentos m ON l.medicamento_id = m.id WHERE l.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY l.fecha_vencimiento ASC");
?>

<style>
    .dashboard-container { padding: 20px; margin-top: 15px; }
    
    .stat-card {
        border: none;
        border-radius: 16px;
        color: white;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        overflow: hidden;
        position: relative;
        cursor: pointer; /* Hace que aparezca la manito */
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    }
    .card-blue { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); cursor: default; }
    .card-orange { background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%); }
    .card-red { background: linear-gradient(135deg, #cb2d3e 0%, #ef473a 100%); }
    .card-green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); cursor: default; }
    
    .stat-icon {
        position: absolute;
        right: 15px;
        bottom: 10px;
        font-size: 80px;
        opacity: 0.15;
    }

    .card-content {
        position: relative;
        z-index: 1;
    }

    /* Animación de Alerta Parpadeante */
    .alerta-parpadeo {
        animation: parpadeo 1.5s infinite;
        background: rgba(255, 255, 255, 0.3);
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 700;
        display: inline-block;
        margin-top: 8px;
        border: 1px solid rgba(255,255,255,0.5);
    }
    @keyframes parpadeo {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
    }

    .custom-card {
        border: none;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        background: #fff;
    }

    .custom-card-header {
        background: transparent;
        border-bottom: 2px solid #f1f1f1;
        padding: 20px 25px;
        font-weight: 700;
        font-size: 1.1rem;
        color: #1e3c72;
    }

    .table thead th {
        font-size: 0.85rem;
        text-transform: uppercase;
        color: #6c757d;
        border-bottom: 2px solid #e9ecef;
        padding: 15px 20px;
    }
    .table tbody td {
        padding: 15px 20px;
    }

    .table tbody tr:hover {
        background: #f8f9fa;
    }
    .btn-gradient-blue { background: linear-gradient(135deg, #1e3c72, #2a5298); color: #fff; border: none; }
    .btn-gradient-blue:hover { color: #fff; opacity: 0.9; }

    .badge-soft-warning { background: #fff3cd; color: #856404; padding: 8px 12px; border-radius: 8px; font-weight: 600; }
    .badge-soft-success { background: #d4edda; color: #155724; padding: 8px 12px; border-radius: 8px; font-weight: 600; }
    
    /* Paginación Bonita (Redonda) */
    .pagination { gap: 6px; }
    .page-item .page-link {
        border-radius: 50%;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #e9ecef;
        color: #1e3c72;
        font-weight: 700;
        transition: all 0.2s;
    }
    .page-item.active .page-link {
        background: linear-gradient(135deg, #1e3c72, #2a5298);
        color: white;
        border-color: transparent;
        box-shadow: 0 4px 10px rgba(30,60,114,0.3);
    }
    .page-item .page-link:hover {
        background: #f8f9fa;
        border-color: #1e3c72;
    }
</style>

<div class="container-fluid px-4 mt-4">
    <div class="card border-0 shadow-lg rounded-4 overflow-hidden position-relative" 
         style="background: linear-gradient(135deg, #1a3a5c 0%, #2d6a9f 100%);">
        <i class="fas fa-pills position-absolute" style="font-size: 150px; right: 20px; top: -30px; opacity: 0.1; color: white;"></i>
        <i class="fas fa-user-shield position-absolute" style="font-size: 100px; right: 180px; bottom: -30px; opacity: 0.1; color: white;"></i>

        <div class="card-body p-4 text-white position-relative">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-white bg-opacity-25 p-3 me-3 shadow">
                    <i class="fas fa-user-shield fa-2x text-white"></i>
                </div>
                <div>
                    <h2 class="display-6 fw-bold mb-1" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.3);">
                        ¡Bienvenido/a, Jefe de Farmacia!
                    </h2>
                    <p class="mb-0 opacity-75">
                        <i class="fas fa-hospital me-2"></i>
                        Centro de Control de Farmacia y Trazabilidad
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid dashboard-container fade-in">
    <div class="row g-4 mb-4">
        <!-- Recetas Pendientes -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card card-blue h-100">
                <div class="card-body">
                    <div class="card-content">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-white-50 mb-1 text-uppercase fw-bold small">Recetas Pendientes</p>
                                <h2 class="fw-bold mb-0 text-white"><?php echo $stats['recetas_pendientes']; ?></h2>
                            </div>
                            <div class="bg-white bg-opacity-25 rounded-circle p-3 d-flex align-items-center justify-content-center">
                                <i class="fas fa-prescription fa-lg text-white"></i>
                            </div>
                        </div>
                    </div>
                    <i class="fas fa-prescription stat-icon"></i>
                </div>
            </div>
        </div>

        <!-- Stock Bajo CON VENTANA MODAL -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card card-orange h-100" data-bs-toggle="modal" data-bs-target="#modalStockBajo">
                <div class="card-body">
                    <div class="card-content">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-white-50 mb-1 text-uppercase fw-bold small">Stock Bajo</p>
                                <h2 class="fw-bold mb-0 text-white"><?php echo $stats['stock_bajo']; ?> <small class="fs-6">lotes</small></h2>
                                <?php if($stats['stock_bajo'] > 0): ?>
                                    <div class="alerta-parpadeo text-white"><i class="fas fa-exclamation-triangle me-1"></i> Clic para ver detalles</div>
                                <?php endif; ?>
                            </div>
                            <div class="bg-white bg-opacity-25 rounded-circle p-3 d-flex align-items-center justify-content-center">
                                <i class="fas fa-box-open fa-lg text-white"></i>
                            </div>
                        </div>
                    </div>
                    <i class="fas fa-box-open stat-icon"></i>
                </div>
            </div>
        </div>

        <!-- Por Vencer CON VENTANA MODAL -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card card-red h-100" data-bs-toggle="modal" data-bs-target="#modalPorVencer">
                <div class="card-body">
                    <div class="card-content">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-white-50 mb-1 text-uppercase fw-bold small">Por Vencer</p>
                                <h2 class="fw-bold mb-0 text-white"><?php echo $stats['por_vencer']; ?> <small class="fs-6">lotes</small></h2>
                                <?php if($stats['por_vencer'] > 0): ?>
                                    <div class="alerta-parpadeo text-white"><i class="fas fa-clock me-1"></i> Clic para ver detalles</div>
                                <?php endif; ?>
                            </div>
                            <div class="bg-white bg-opacity-25 rounded-circle p-3 d-flex align-items-center justify-content-center">
                                <i class="fas fa-calendar-times fa-lg text-white"></i>
                            </div>
                        </div>
                    </div>
                    <i class="fas fa-calendar-times stat-icon"></i>
                </div>
            </div>
        </div>

        <!-- Pacientes Activos -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card card-green h-100">
                <div class="card-body">
                    <div class="card-content">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-white-50 mb-1 text-uppercase fw-bold small">Pacientes Activos</p>
                                <h2 class="fw-bold mb-0 text-white"><?php echo $stats['total_pacientes']; ?></h2>
                            </div>
                            <div class="bg-white bg-opacity-25 rounded-circle p-3 d-flex align-items-center justify-content-center">
                                <i class="fas fa-users fa-lg text-white"></i>
                            </div>
                        </div>
                    </div>
                    <i class="fas fa-users stat-icon"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- LISTADO DE RECETAS A TODO EL ANCHO -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card custom-card h-100">
                <div class="card-header custom-card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-file-prescription text-primary me-2"></i>Recetas Recientes</span>
                    <a href="recetas_recibidas.php" class="btn btn-gradient-blue btn-sm rounded-pill px-3">Ver todas</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Paciente</th>
                                    <th>Doctor</th>
                                    <th>Fecha</th>
                                    <th>Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($recetas_recientes->num_rows > 0): ?>
                                    <?php while($receta = $recetas_recientes->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-dark">
                                            <?php echo $receta['paciente_nombre'] . ' ' . $receta['paciente_apellido']; ?>
                                        </td>
                                        <td>
                                            <span class="text-muted small">
                                                <i class="fas fa-user-md me-1 text-secondary"></i>
                                                <?php echo $receta['doctor_nombre']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($receta['fecha_receta'])); ?></td>
                                        <td>
                                            <?php
                                            $badge_class = '';
                                            $icon = '';
                                            if($receta['estado'] == 'pendiente') { $badge_class = 'badge-soft-warning'; $icon = 'fa-clock'; }
                                            elseif($receta['estado'] == 'aprobada') { $badge_class = 'badge-soft-success'; $icon = 'fa-check-circle'; }
                                            else { $badge_class = 'badge-soft-secondary'; $icon = 'fa-circle'; }
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>">
                                                <i class="fas <?php echo $icon; ?> me-1"></i>
                                                <?php echo ucfirst($receta['estado']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-outline-info rounded-circle" onclick="verReceta(<?php echo $receta['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">No hay recetas registradas.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Paginación -->
                <?php if($total_paginas > 1): ?>
                <div class="card-footer bg-white border-0 py-3">
                    <nav>
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?pagina=<?php echo $pagina - 1; ?>"><i class="fas fa-chevron-left"></i></a>
                            </li>
                            <?php for($i = 1; $i <= $total_paginas; $i++): ?>
                                <li class="page-item <?php echo $pagina == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $pagina >= $total_paginas ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?pagina=<?php echo $pagina + 1; ?>"><i class="fas fa-chevron-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- VENTANA MODAL: STOCK BAJO                  -->
<!-- ========================================== -->
<div class="modal fade" id="modalStockBajo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header border-0" style="background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%);">
                <h5 class="modal-title text-white fw-bold"><i class="fas fa-exclamation-triangle me-2"></i> Alertas de Stock Bajo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Medicamento</th>
                            <th class="text-center">Stock Actual</th>
                            <th>Fecha Vencimiento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($lista_stock_bajo->num_rows > 0): ?>
                            <?php while($med = $lista_stock_bajo->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark"><i class="fas fa-capsules text-warning me-2"></i><?php echo $med['nombre']; ?></td>
                                    <td class="text-center"><span class="badge bg-warning text-dark px-3 py-2 rounded-pill fs-6"><?php echo $med['cantidad']; ?> uds</span></td>
                                    <td><?php echo date('d/m/Y', strtotime($med['fecha_vencimiento'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center py-4 text-muted">No hay productos con stock bajo.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cerrar</button>
                <a href="inventario.php" class="btn btn-warning rounded-pill px-4 text-white">Ir al Inventario</a>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- VENTANA MODAL: POR VENCER                  -->
<!-- ========================================== -->
<div class="modal fade" id="modalPorVencer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header border-0" style="background: linear-gradient(135deg, #cb2d3e 0%, #ef473a 100%);">
                <h5 class="modal-title text-white fw-bold"><i class="fas fa-calendar-times me-2"></i> Lotes Próximos a Vencer (30 días)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Medicamento</th>
                            <th class="text-center">Stock Actual</th>
                            <th>Fecha Vencimiento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($lista_por_vencer->num_rows > 0): ?>
                            <?php while($med = $lista_por_vencer->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark"><i class="fas fa-capsules text-danger me-2"></i><?php echo $med['nombre']; ?></td>
                                    <td class="text-center"><span class="badge bg-danger px-3 py-2 rounded-pill fs-6"><?php echo $med['cantidad']; ?> uds</span></td>
                                    <td class="text-danger fw-bold"><?php echo date('d/m/Y', strtotime($med['fecha_vencimiento'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center py-4 text-muted">No hay lotes próximos a vencer.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cerrar</button>
                <a href="lotes.php" class="btn btn-danger rounded-pill px-4 text-white">Ir a Lotes</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function verReceta(id) {
    Swal.fire({
        title: 'Detalles de la Receta',
        html: 'Cargando...',
        didOpen: async () => {
            Swal.showLoading();
            setTimeout(() => {
                Swal.fire({
                    title: 'Receta #' + id,
                    html: `
                        <div class="text-left">
                            <p><strong>Paciente:</strong> Juan Pérez</p>
                            <p><strong>Doctor:</strong> Dr. Juan Pérez</p>
                            <p><strong>Medicamentos:</strong></p>
                            <ul>
                                <li>Paracetamol 500mg - 10 tabletas</li>
                            </ul>
                            <p><strong>Fecha:</strong> <?php echo date('d/m/Y'); ?></p>
                        </div>
                    `,
                    confirmButtonText: 'Aceptar',
                    confirmButtonColor: '#1e3c72'
                });
            }, 1500);
        }
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>