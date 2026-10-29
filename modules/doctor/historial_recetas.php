<?php
require_once '../../config/database.php';
$titulo_pagina = "Mi Historial de Recetas";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] != 'doctor') {
    header("Location: ../../login.php");
    exit();
}

$doctor_id = $_SESSION['usuario_id'];

$tiene_doctor_id = false;
$result = $conn->query("SHOW COLUMNS FROM recetas LIKE 'doctor_id'");
if ($result && $result->num_rows > 0) {
    $tiene_doctor_id = true;
}

if (!$tiene_doctor_id) {
    echo '<div class="alert alert-danger">Error: La tabla recetas no tiene la columna doctor_id. Contacta al administrador.</div>';
    require_once '../../includes/footer.php';
    exit();
}

$estado_filtro = $_GET['estado'] ?? 'todos';
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$buscar = $_GET['buscar'] ?? '';

$where = "WHERE r.doctor_id = ?";
$params = [$doctor_id];
$types = "i";

if ($estado_filtro != 'todos') {
    $where .= " AND r.estado = ?";
    $params[] = $estado_filtro;
    $types .= "s";
}
if ($fecha_inicio) {
    $where .= " AND r.fecha_receta >= ?";
    $params[] = $fecha_inicio;
    $types .= "s";
}
if ($fecha_fin) {
    $where .= " AND r.fecha_receta <= ?";
    $params[] = $fecha_fin;
    $types .= "s";
}
if ($buscar) {
    $where .= " AND (p.nombre LIKE ? OR p.apellido LIKE ? OR p.telefono LIKE ?)";
    $busqueda = "%$buscar%";
    $params[] = $busqueda;
    $params[] = $busqueda;
    $params[] = $busqueda;
    $types .= "sss";
}

$stats = [
    'total_recetas' => 0,
    'pendientes' => 0,
    'aprobadas' => 0,
    'entregadas' => 0,
    'rechazadas' => 0
];

$sql_stats = "SELECT 
                COUNT(*) as total_recetas,
                SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN estado = 'aprobada' THEN 1 ELSE 0 END) as aprobadas,
                SUM(CASE WHEN estado = 'entregada' THEN 1 ELSE 0 END) as entregadas,
                SUM(CASE WHEN estado = 'rechazada' THEN 1 ELSE 0 END) as rechazadas
              FROM recetas
              WHERE doctor_id = ?";
$stmt_stats = $conn->prepare($sql_stats);
if ($stmt_stats) {
    $stmt_stats->bind_param("i", $doctor_id);
    $stmt_stats->execute();
    $result_stats = $stmt_stats->get_result();
    if ($result_stats) {
        $stats = $result_stats->fetch_assoc();
    }
    $stmt_stats->close();
}

$por_pagina = 6;
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$offset = ($pagina - 1) * $por_pagina;

$total_recetas = 0;
$total_paginas = 0;
$recetas = null;

$sql_count = "SELECT COUNT(*) as total FROM recetas r JOIN pacientes p ON r.paciente_id = p.id $where";
$stmt_count = $conn->prepare($sql_count);
if ($stmt_count) {
    if (!empty($params)) {
        $stmt_count->bind_param($types, ...$params);
    }
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    if ($result_count) {
        $row = $result_count->fetch_assoc();
        $total_recetas = $row['total'] ?? 0;
    }
    $stmt_count->close();
}

$total_paginas = ceil($total_recetas / $por_pagina);

$sql = "SELECT r.*, 
               CONCAT(p.nombre, ' ', p.apellido) AS paciente_nombre,
               p.edad, p.sexo, p.telefono, p.telefono_tutor
        FROM recetas r
        JOIN pacientes p ON r.paciente_id = p.id
        $where
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?";
$params[] = $por_pagina;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $recetas = $stmt->get_result();
    $stmt->close();
}
?>

<div class="historial-container">

    <div class="historial-hero">
        <div class="historial-hero-content">
            <div class="historial-hero-icon">
                <i class="fas fa-prescription-bottle"></i>
            </div>
            <div>
                <h1 class="historial-hero-title">Mi Historial de Recetas</h1>
                <p class="historial-hero-subtitle">Gestiona y revisa todas tus recetas médicas</p>
            </div>
        </div>
    </div>

    <div class="historial-stats">
        <div class="historial-stat-card">
            <div class="historial-stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                <i class="fas fa-prescription"></i>
            </div>
            <div>
                <h3><?php echo $stats['total_recetas'] ?? 0; ?></h3>
                <small>Total</small>
            </div>
        </div>
        <div class="historial-stat-card">
            <div class="historial-stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                <i class="fas fa-clock"></i>
            </div>
            <div>
                <h3><?php echo $stats['pendientes'] ?? 0; ?></h3>
                <small>Pendientes</small>
            </div>
        </div>
        <div class="historial-stat-card">
            <div class="historial-stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <h3><?php echo $stats['aprobadas'] ?? 0; ?></h3>
                <small>Aprobadas</small>
            </div>
        </div>
        <div class="historial-stat-card">
            <div class="historial-stat-icon" style="background: rgba(6, 182, 212, 0.1); color: #06b6d4;">
                <i class="fas fa-truck"></i>
            </div>
            <div>
                <h3><?php echo $stats['entregadas'] ?? 0; ?></h3>
                <small>Entregadas</small>
            </div>
        </div>
        <div class="historial-stat-card">
            <div class="historial-stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                <i class="fas fa-times-circle"></i>
            </div>
            <div>
                <h3><?php echo $stats['rechazadas'] ?? 0; ?></h3>
                <small>Rechazadas</small>
            </div>
        </div>
        <div class="historial-stat-card">
            <div class="historial-stat-icon" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                <i class="fas fa-chart-line"></i>
            </div>
            <div>
                <h3>
                    <?php 
                    $exitosas = ($stats['aprobadas'] ?? 0) + ($stats['entregadas'] ?? 0);
                    $total = $stats['total_recetas'] ?? 0;
                    echo $total > 0 ? round(($exitosas / $total) * 100) . '%' : '0%';
                    ?>
                </h3>
                <small>Tasa Éxito</small>
            </div>
        </div>
    </div>

    <div class="historial-card">
        <div class="historial-card-header">
            <h6><i class="fas fa-sliders-h me-2"></i>Filtros de Búsqueda</h6>
        </div>
        <div class="historial-card-body">
            <form method="GET" action="" class="historial-filters">
                <div class="historial-filter-group">
                    <label>Estado</label>
                    <select name="estado" class="historial-select">
                        <option value="todos" <?php echo $estado_filtro == 'todos' ? 'selected' : ''; ?>>Todos</option>
                        <option value="pendiente" <?php echo $estado_filtro == 'pendiente' ? 'selected' : ''; ?>>Pendientes</option>
                        <option value="aprobada" <?php echo $estado_filtro == 'aprobada' ? 'selected' : ''; ?>>Aprobadas</option>
                        <option value="entregada" <?php echo $estado_filtro == 'entregada' ? 'selected' : ''; ?>>Entregadas</option>
                        <option value="rechazada" <?php echo $estado_filtro == 'rechazada' ? 'selected' : ''; ?>>Rechazadas</option>
                    </select>
                </div>
                <div class="historial-filter-group">
                    <label>Fecha Inicio</label>
                    <input type="date" name="fecha_inicio" class="historial-input" value="<?php echo $fecha_inicio; ?>">
                </div>
                <div class="historial-filter-group">
                    <label>Fecha Fin</label>
                    <input type="date" name="fecha_fin" class="historial-input" value="<?php echo $fecha_fin; ?>">
                </div>
                <div class="historial-filter-group">
                    <label>Buscar paciente</label>
                    <input type="text" name="buscar" class="historial-input" placeholder="Nombre, apellido o teléfono" value="<?php echo htmlspecialchars($buscar); ?>">
                </div>
                <div class="historial-filter-group historial-filter-submit">
                    <button type="submit" class="historial-btn historial-btn-primary"><i class="fas fa-search me-1"></i> Buscar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="historial-card">
        <div class="historial-card-header">
            <h6><i class="fas fa-list me-2"></i>Historial de Recetas</h6>
            <span class="historial-badge"><?php echo $total_recetas; ?> recetas</span>
        </div>
        <div class="historial-card-body">
            <div class="historial-table-wrapper">
                <table class="historial-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Paciente</th>
                            <th>Edad/Sexo</th>
                            <th>Teléfono</th>
                            <th>Estado</th>
                            <th>Firma</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recetas && $recetas->num_rows > 0): ?>
                            <?php while ($receta = $recetas->fetch_assoc()): ?>
                                <tr>
                                    <td><span class="historial-id">#<?php echo $receta['id']; ?></span></td>
                                    <td><?php echo date('d/m/Y', strtotime($receta['fecha_receta'])); ?></td>
                                    <td>
                                        <div class="historial-paciente">
                                            <span class="historial-avatar" style="background: <?php echo '#' . substr(md5($receta['paciente_nombre']), 0, 6); ?>;">
                                                <?php echo strtoupper(substr($receta['paciente_nombre'], 0, 2)); ?>
                                            </span>
                                            <?php echo htmlspecialchars($receta['paciente_nombre']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo $receta['edad']; ?> años
                                        <small><?php echo $receta['sexo'] == 'M' ? 'M' : ($receta['sexo'] == 'F' ? 'F' : 'N/A'); ?></small>
                                    </td>
                                    <td>
                                        <?php 
                                            $telefono_mostrar = $receta['telefono'];
                                            if (empty($telefono_mostrar) || $telefono_mostrar == '0' || trim($telefono_mostrar) == '') {
                                                if (!empty($receta['telefono_tutor'])) {
                                                    $telefono_mostrar = $receta['telefono_tutor'] . ' <small class="text-muted">(Tutor)</small>';
                                                } else {
                                                    $telefono_mostrar = '<span class="text-muted">N/A</span>';
                                                }
                                            }
                                            echo $telefono_mostrar;
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $badge_class = match($receta['estado']) {
                                            'pendiente' => 'pendiente',
                                            'aprobada' => 'aprobada',
                                            'entregada' => 'entregada',
                                            'rechazada' => 'rechazada',
                                            default => 'secondary'
                                        };
                                        $icon = match($receta['estado']) {
                                            'pendiente' => 'clock',
                                            'aprobada' => 'check-circle',
                                            'entregada' => 'truck',
                                            'rechazada' => 'times-circle',
                                            default => 'circle'
                                        };
                                        ?>
                                        <span class="historial-estado historial-estado-<?php echo $badge_class; ?>">
                                            <i class="fas fa-<?php echo $icon; ?> me-1"></i>
                                            <?php echo ucfirst($receta['estado']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="historial-btn-sm historial-btn-outline" onclick="verFirma('<?php echo addslashes($receta['firma_electronica']); ?>')">
                                            <i class="fas fa-signature"></i> Ver
                                        </button>
                                    </td>
                                    <td class="text-end">
                                        <button class="historial-btn-sm historial-btn-info" onclick="verDetalles(<?php echo $receta['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($receta['estado'] == 'pendiente'): ?>
                                            <button class="historial-btn-sm historial-btn-warning" onclick="editarReceta(<?php echo $receta['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="historial-btn-sm historial-btn-success" onclick="imprimirReceta(<?php echo $receta['id']; ?>)">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="historial-empty-table">
                                    <i class="fas fa-prescription"></i>
                                    <h5>No hay recetas que coincidan con los filtros</h5>
                                    <p>Prueba a cambiar los criterios de búsqueda</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_paginas > 1): ?>
                <div class="historial-pagination">
                    <ul>
                        <li class="<?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                            <a href="?pagina=<?php echo $pagina-1; ?>&estado=<?php echo $estado_filtro; ?>&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&buscar=<?php echo urlencode($buscar); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <li class="<?php echo $pagina == $i ? 'active' : ''; ?>">
                                <a href="?pagina=<?php echo $i; ?>&estado=<?php echo $estado_filtro; ?>&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&buscar=<?php echo urlencode($buscar); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        <li class="<?php echo $pagina >= $total_paginas ? 'disabled' : ''; ?>">
                            <a href="?pagina=<?php echo $pagina+1; ?>&estado=<?php echo $estado_filtro; ?>&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&buscar=<?php echo urlencode($buscar); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    /* FORZAMOS EL CONTENEDOR PARA QUE SUBA Y SE PEGUE ARRIBA */
    .historial-container {
        padding: 0 25px 25px 25px;
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        background: #f0f9ff;
        min-height: 100vh;
        margin-top: -35px; /* MARGEN NEGATIVO PARA SUBIRLO */
    }

    .historial-hero {
        background: linear-gradient(135deg, #0c4a6e 0%, #0891b2 50%, #22d3ee 100%);
        border-radius: 20px;
        padding: 30px 35px;
        margin-bottom: 25px;
        box-shadow: 0 15px 40px rgba(8, 145, 178, 0.3);
        margin-top: 0;
    }
    .historial-hero-content {
        display: flex;
        align-items: center;
        gap: 20px;
        color: #fff;
    }
    .historial-hero-icon {
        background: rgba(255,255,255,0.2);
        border-radius: 50%;
        padding: 18px;
        font-size: 32px;
    }
    .historial-hero-title {
        font-size: 28px;
        font-weight: 700;
        margin: 0;
        text-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    .historial-hero-subtitle {
        margin: 5px 0 0 0;
        opacity: 0.9;
        font-size: 16px;
    }

    .historial-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        margin-bottom: 25px;
        border: 1px solid rgba(8, 145, 178, 0.1);
        overflow: hidden;
    }
    .historial-card-header {
        padding: 18px 25px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    .historial-card-header h6 {
        margin: 0;
        font-weight: 600;
        color: #0c4a6e;
        font-size: 16px;
    }
    .historial-card-body {
        padding: 20px 25px;
    }

    .historial-badge {
        background: #0891b2;
        color: #fff;
        padding: 5px 16px;
        border-radius: 50px;
        font-size: 13px;
        font-weight: 600;
    }

    .historial-stats {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 15px;
        margin-bottom: 25px;
    }
    .historial-stat-card {
        background: #fff;
        border-radius: 14px;
        padding: 18px 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        border: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 14px;
        transition: all 0.2s;
    }
    .historial-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.06);
    }
    .historial-stat-icon {
        border-radius: 12px;
        padding: 12px;
        font-size: 22px;
        flex-shrink: 0;
    }
    .historial-stat-card h3 {
        margin: 0;
        font-size: 22px;
        font-weight: 700;
        color: #0f172a;
    }
    .historial-stat-card small {
        color: #94a3b8;
        font-size: 13px;
        display: block;
        margin-top: 2px;
    }

    .historial-filters {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr 1.5fr 0.7fr;
        gap: 15px;
        align-items: end;
    }
    .historial-filter-group label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #475569;
        margin-bottom: 5px;
    }
    .historial-select,
    .historial-input {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #f8fafc;
        font-size: 14px;
        transition: all 0.2s;
    }
    .historial-select:focus,
    .historial-input:focus {
        outline: none;
        border-color: #0891b2;
        box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.15);
        background: #fff;
    }
    .historial-filter-submit {
        display: flex;
        align-items: flex-end;
    }

    .historial-btn {
        padding: 10px 24px;
        border-radius: 10px;
        border: none;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        display: inline-block;
        text-align: center;
    }
    .historial-btn-primary {
        background: linear-gradient(135deg, #0c4a6e 0%, #0891b2 100%);
        color: #fff;
        width: 100%;
    }
    .historial-btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(8, 145, 178, 0.35);
    }
    .historial-btn-outline {
        background: transparent;
        color: #0891b2;
        border: 1px solid #0891b2;
    }
    .historial-btn-outline:hover {
        background: #0891b2;
        color: #fff;
    }
    .historial-btn-sm {
        padding: 5px 12px;
        border-radius: 8px;
        border: none;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.2s;
        margin: 0 3px;
        text-decoration: none;
        display: inline-block;
    }
    .historial-btn-info {
        background: #dbeafe;
        color: #1d4ed8;
    }
    .historial-btn-info:hover {
        background: #bfdbfe;
    }
    .historial-btn-warning {
        background: #fef3c7;
        color: #d97706;
    }
    .historial-btn-warning:hover {
        background: #fde68a;
    }
    .historial-btn-success {
        background: #d1fae5;
        color: #059669;
    }
    .historial-btn-success:hover {
        background: #a7f3d0;
    }

    .historial-table-wrapper {
        overflow-x: auto;
    }
    .historial-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    .historial-table thead {
        background: #f1f5f9;
        border-radius: 12px;
    }
    .historial-table thead th {
        padding: 14px 16px;
        text-align: left;
        font-weight: 600;
        color: #475569;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .historial-table tbody tr {
        border-bottom: 1px solid #f1f5f9;
        transition: all 0.15s;
    }
    .historial-table tbody tr:hover {
        background: #f8fafc;
    }
    .historial-table tbody td {
        padding: 14px 16px;
        vertical-align: middle;
    }
    .historial-id {
        background: #e2e8f0;
        padding: 3px 12px;
        border-radius: 50px;
        font-weight: 600;
        font-size: 13px;
        color: #475569;
    }
    .historial-paciente {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .historial-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-weight: 700;
        font-size: 13px;
        flex-shrink: 0;
    }

    .historial-estado {
        padding: 4px 14px;
        border-radius: 50px;
        font-size: 13px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
    }
    .historial-estado-pendiente {
        background: #fef3c7;
        color: #d97706;
    }
    .historial-estado-aprobada {
        background: #d1fae5;
        color: #059669;
    }
    .historial-estado-entregada {
        background: #dbeafe;
        color: #1d4ed8;
    }
    .historial-estado-rechazada {
        background: #fee2e2;
        color: #dc2626;
    }

    .historial-empty-table {
        text-align: center;
        padding: 40px 0 !important;
        color: #94a3b8;
    }
    .historial-empty-table i {
        font-size: 48px;
        display: block;
        margin-bottom: 15px;
        color: #22d3ee;
    }
    .historial-empty-table h5 {
        margin: 0 0 5px 0;
        font-weight: 600;
        color: #64748b;
    }
    .historial-empty-table p {
        margin: 0;
        font-size: 14px;
    }

    .historial-pagination {
        margin-top: 30px;
        display: flex;
        justify-content: center;
    }
    .historial-pagination ul {
        display: flex;
        list-style: none;
        padding: 0;
        gap: 8px;
        align-items: center;
    }
    .historial-pagination ul li a {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: #fff;
        color: #0891b2;
        text-decoration: none;
        font-weight: 700;
        font-size: 15px;
        border: 2px solid #e2e8f0;
        transition: all 0.3s ease;
        box-shadow: 0 2px 5px rgba(0,0,0,0.02);
    }
    .historial-pagination ul li a:hover {
        background: #f0f9ff;
        border-color: #0891b2;
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(8, 145, 178, 0.2);
    }
    .historial-pagination ul li.active a {
        background: linear-gradient(135deg, #0c4a6e 0%, #0891b2 100%);
        color: #fff;
        border-color: transparent;
        box-shadow: 0 5px 15px rgba(8, 145, 178, 0.4);
        transform: scale(1.1);
    }
    .historial-pagination ul li.disabled a {
        opacity: 0.5;
        cursor: not-allowed;
        background: #f8fafc;
        border-color: #e2e8f0;
        color: #94a3b8;
    }
    .historial-pagination ul li.disabled a:hover {
        transform: none;
        box-shadow: none;
        background: #f8fafc;
        border-color: #e2e8f0;
    }

    @media (max-width: 1024px) {
        .historial-stats {
            grid-template-columns: repeat(3, 1fr);
        }
        .historial-filters {
            grid-template-columns: 1fr 1fr;
        }
    }
    @media (max-width: 768px) {
        .historial-stats {
            grid-template-columns: repeat(2, 1fr);
        }
        .historial-filters {
            grid-template-columns: 1fr;
        }
        .historial-hero {
            padding: 20px;
        }
        .historial-hero-title {
            font-size: 22px;
        }
        .historial-container {
            padding: 10px 15px;
            margin-top: -15px;
        }
        .historial-card-body {
            padding: 15px;
        }
        .historial-table thead {
            display: none;
        }
        .historial-table tbody tr {
            display: block;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .historial-table tbody td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .historial-table tbody td:last-child {
            border-bottom: none;
        }
        .historial-table tbody td::before {
            content: attr(data-label);
            font-weight: 600;
            color: #475569;
            font-size: 12px;
        }
        .historial-paciente {
            justify-content: flex-end;
        }
        .historial-empty-table {
            display: block !important;
        }
    }
    @media (max-width: 480px) {
        .historial-stats {
            grid-template-columns: 1fr 1fr;
        }
        .historial-stat-card {
            padding: 12px 15px;
        }
        .historial-stat-card h3 {
            font-size: 18px;
        }
        .historial-stat-icon {
            padding: 8px;
            font-size: 18px;
        }
    }
</style>

<script>
function verFirma(firma) {
    Swal.fire({
        title: 'Firma Electrónica',
        html: `
            <div class="text-start">
                <div class="bg-light rounded-4 p-3 mb-3">
                    <p class="mb-0"><i class="fas fa-signature text-primary me-2"></i><strong>Firma Digital:</strong></p>
                    <p class="mb-0 text-break">${firma || 'No registrada'}</p>
                </div>
                <p class="text-muted small mb-0"><i class="fas fa-info-circle me-1"></i>Esta firma certifica la autenticidad de la receta digital</p>
            </div>
        `,
        icon: 'info',
        confirmButtonText: 'Cerrar',
        confirmButtonColor: '#0891b2',
        width: '450px',
    });
}

function verDetalles(id) {
    window.location.href = 'detalle_receta.php?id=' + id;
}

function editarReceta(id) {
    Swal.fire({
        title: '¿Editar receta?',
        text: 'La receta será modificada y deberá ser aprobada nuevamente',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, editar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#6c757d',
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'crear_receta.php?editar=' + id;
        }
    });
}

function imprimirReceta(id) {
    Swal.fire({
        title: 'Imprimir Receta',
        text: '¿Deseas imprimir esta receta?',
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Sí, imprimir',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#059669',
        cancelButtonColor: '#6c757d',
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire('Éxito', 'Receta enviada a impresión', 'success');
        }
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>