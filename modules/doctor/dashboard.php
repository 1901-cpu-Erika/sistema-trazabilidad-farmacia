<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Managua');
require_once '../../config/database.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'doctor') {
    header("Location: ../../login.php");
    exit();
}

$doctor_id = $_SESSION['usuario_id'];

if (isset($_GET['marcar_leidas']) && $_GET['marcar_leidas'] == 1) {
    $sql_update = "UPDATE notificaciones n 
                   JOIN recetas r ON n.paciente_id = r.paciente_id 
                   SET n.leida = 1 
                   WHERE r.doctor_id = ? AND n.leida = 0";
    $stmt_u = $conn->prepare($sql_update);
    if ($stmt_u) {
        $stmt_u->bind_param("i", $doctor_id);
        $stmt_u->execute();
        $stmt_u->close();
    }
    echo json_encode(['status' => 'success']);
    exit(); 
}


$titulo_pagina = "Dashboard - Doctor";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$nombre_completo = 'Usuario';
$especialidad_doctor = 'Sin especialidad';

$sql_doctor = "SELECT u.nombre, u.especialidad_id, e.nombre as especialidad_nombre
               FROM usuarios u
               LEFT JOIN especialidades e ON u.especialidad_id = e.id
               WHERE u.id = ?";
$stmt_doctor = $conn->prepare($sql_doctor);
if ($stmt_doctor) {
    $stmt_doctor->bind_param("i", $doctor_id);
    $stmt_doctor->execute();
    $result_doctor = $stmt_doctor->get_result();
    if ($result_doctor && $result_doctor->num_rows > 0) {
        $doctor_data = $result_doctor->fetch_assoc();
        if (!empty($doctor_data['nombre'])) {
            $nombre_completo = $doctor_data['nombre'];
        }
        if (!empty($doctor_data['especialidad_nombre'])) {
            $especialidad_doctor = $doctor_data['especialidad_nombre'];
        }
    }
    $stmt_doctor->close();
}

if (!empty($nombre_completo) && $nombre_completo !== 'Usuario') {
    $nombre_doctor = 'Dr. ' . $nombre_completo;
} else {
    $nombre_doctor = 'Dr. Usuario';
}

function fecha_espanol($timestamp = null)
{
    if ($timestamp === null) {
        $timestamp = time();
    }

    $dias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    $meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

    $dia_semana = $dias[date('w', $timestamp)];
    $dia = date('d', $timestamp);
    $mes = $meses[date('n', $timestamp) - 1];
    $anio = date('Y', $timestamp);

    return $dia_semana . ', ' . $dia . '/' . str_pad(date('n', $timestamp), 2, '0', STR_PAD_LEFT) . '/' . $anio;
}

$total_recetas = 0;
$recetas_pendientes = 0;
$pacientes_atendidos = 0;

$sql = "SELECT COUNT(*) AS total FROM recetas WHERE doctor_id = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $total_recetas = $row['total'] ?? 0;
    $stmt->close();
}

$sql = "SELECT COUNT(*) AS total FROM recetas WHERE doctor_id = ? AND estado = 'pendiente'";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $recetas_pendientes = $row['total'] ?? 0;
    $stmt->close();
}

$sql = "SELECT COUNT(DISTINCT paciente_id) AS total FROM recetas WHERE doctor_id = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $pacientes_atendidos = $row['total'] ?? 0;
    $stmt->close();
}

$num_notificaciones = 0;
$notificaciones_recientes = [];

$sql_notif = "SELECT DISTINCT n.id, n.mensaje, n.fecha 
              FROM notificaciones n
              JOIN recetas r ON n.paciente_id = r.paciente_id
              WHERE r.doctor_id = ? AND n.leida = 0 
              ORDER BY n.fecha DESC LIMIT 8";
$stmt_n = $conn->prepare($sql_notif);
if ($stmt_n) {
    $stmt_n->bind_param("i", $doctor_id);
    $stmt_n->execute();
    $res_n = $stmt_n->get_result();
    $num_notificaciones = $res_n->num_rows;
    while ($row_n = $res_n->fetch_assoc()) {
        $notificaciones_recientes[] = $row_n;
    }
    $stmt_n->close();
}

$por_pagina = 6;
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina - 1) * $por_pagina;

$total_registros = 0;
$sql_count = "SELECT COUNT(*) AS total FROM recetas WHERE doctor_id = ?";
$stmt_count = $conn->prepare($sql_count);
if ($stmt_count) {
    $stmt_count->bind_param("i", $doctor_id);
    $stmt_count->execute();
    $row = $stmt_count->get_result()->fetch_assoc();
    $total_registros = $row['total'] ?? 0;
    $stmt_count->close();
}
$total_paginas = ceil($total_registros / $por_pagina);

$sql_ultimas = "
    SELECT 
        r.id,
        r.fecha_receta,
        r.estado,
        CONCAT(p.nombre, ' ', p.apellido) AS paciente_nombre
    FROM recetas r
    JOIN pacientes p ON r.paciente_id = p.id
    WHERE r.doctor_id = ?
    ORDER BY r.fecha_receta DESC
    LIMIT ? OFFSET ?
";
$stmt_ultimas = $conn->prepare($sql_ultimas);
if ($stmt_ultimas) {
    $stmt_ultimas->bind_param("iii", $doctor_id, $por_pagina, $offset);
    $stmt_ultimas->execute();
    $ultimas_recetas = $stmt_ultimas->get_result();
} else {
    $ultimas_recetas = false;
}
?>

<style>
    html, body {
        height: auto !important;
        min-height: 100% !important;
        overflow-x: hidden !important;
        padding-top: 0 !important;
        margin-top: 0 !important;
    }

    .main-content {
        min-height: auto !important;
        height: auto !important;
        overflow-y: visible !important;
        padding-top: 0 !important;
        margin-top: 0 !important;
    }

    .container-fluid.page-top {
        padding-top: 0 !important;
        margin-top: -25px !important;
    }

    .glass-card {
        background: rgba(255,255,255,0.95);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255,255,255,0.3);
    }

    .stat-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 30px rgba(0,0,0,0.08) !important;
    }

    .avatar-sm {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-weight: 700;
        font-size: 12px;
        flex-shrink: 0;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }

    .table tbody tr {
        transition: all 0.15s ease;
        border-bottom: 1px solid rgba(0,0,0,0.03);
    }

    .table tbody tr:hover {
        background: rgba(78, 115, 223, 0.04);
        transform: scale(1.001);
    }

    .table thead th {
        font-weight: 600;
        color: #4a5b7a;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding-top: 14px;
        padding-bottom: 14px;
    }

    .bg-primary-subtle { background: #d1ecf1 !important; color: #0c5460 !important; }
    .bg-warning-subtle { background: #fff3cd !important; color: #856404 !important; }
    .bg-success-subtle { background: #d4edda !important; color: #155724 !important; }
    .bg-danger-subtle  { background: #f8d7da !important; color: #721c24 !important; }
    .bg-info-subtle    { background: #d1ecf1 !important; color: #0c5460 !important; }
    .bg-secondary-subtle { background: #e2e3e5 !important; color: #383d41 !important; }

    .fade-in { animation: fadeUp 0.6s ease forwards; }

    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(20px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .display-6 { font-size: 2rem; font-weight: 600; }
    .opacity-10 { opacity: 0.1; }
    .progress { background: rgba(0,0,0,0.05); border-radius: 8px; }

    .pagination { gap: 4px; }

    .page-item.active .page-link {
        background: linear-gradient(135deg, #1a3a5c, #2d6a9f);
        border-color: #2d6a9f;
        color: #fff;
        box-shadow: 0 4px 12px rgba(45, 106, 159, 0.3);
        border-radius: 50px;
    }

    .page-link {
        border: none;
        color: #4a5b7a;
        background: #f8f9fa;
        padding: 8px 16px;
        font-weight: 600;
        border-radius: 50px;
        transition: all 0.25s;
    }

    .page-link:hover {
        background: #e9ecef;
        color: #1a3a5c;
        transform: translateY(-2px);
    }

    .page-item.disabled .page-link {
        opacity: 0.4;
        cursor: not-allowed;
        transform: none;
        background: #f8f9fa;
    }

    @keyframes ring-bell {
        0% { transform: rotate(0); }
        10% { transform: rotate(15deg); }
        20% { transform: rotate(-10deg); }
        30% { transform: rotate(5deg); }
        40% { transform: rotate(-5deg); }
        50% { transform: rotate(0); }
        100% { transform: rotate(0); }
    }

    .bell-shake {
        display: inline-block;
        transform-origin: top center;
        animation: ring-bell 2s infinite;
    }

    .notif-dropdown {
        animation: fadeInDown 0.3s ease;
        border-radius: 16px;
        border: none;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    }

    @keyframes fadeInDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .notif-header {
        background: linear-gradient(135deg, #1a3a5c 0%, #2d6a9f 100%);
        color: white;
        padding: 16px 20px;
        border-radius: 16px 16px 0 0;
    }

    .notif-item {
        padding: 14px 20px;
        border-bottom: 1px solid #f1f5f9;
        transition: all 0.2s ease;
        text-decoration: none;
        display: block;
    }

    .notif-item:hover {
        background: #f8fafc;
        border-left: 4px solid #2d6a9f;
    }

    .notif-text {
        color: #1e293b;
        font-size: 0.85rem;
        font-weight: 500;
        line-height: 1.4;
        margin-bottom: 5px;
        white-space: normal;
    }

    .notif-meta {
        color: #64748b;
        font-size: 0.72rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .notif-scroll::-webkit-scrollbar {
        width: 6px;
    }
    .notif-scroll::-webkit-scrollbar-track {
        background: #f1f5f9;
    }
    .notif-scroll::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }
    .notif-scroll::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
</style>

<div class="container-fluid px-4 fade-in page-top">

    <div class="row g-3 mb-3 position-relative" style="z-index: 100;">
        <div class="col-12">
            <div class="card border-0 shadow-lg rounded-4"
                 style="background: linear-gradient(135deg, #1a3a5c 0%, #2d6a9f 100%); position: relative;">
                
                <div class="position-absolute w-100 h-100 rounded-4" style="top: 0; left: 0; overflow: hidden; pointer-events: none; z-index: 0;">
                    <div class="position-absolute top-0 end-0 opacity-10" style="font-size: 120px; right: 20px; top: -20px;">
                        <i class="fas fa-user-md"></i>
                    </div>
                </div>

                <div class="card-body p-4 text-white position-relative d-flex justify-content-between align-items-center flex-wrap gap-3" style="z-index: 1;">
                    
                    <div class="d-flex align-items-center position-relative" style="z-index: 2;">
                        <div class="rounded-circle bg-white bg-opacity-20 p-3 me-3 shadow">
                            <i class="fas fa-user-md fa-2x text-white"></i>
                        </div>

                        <div>
                            <h2 class="display-6 fw-bold mb-1">
                                ¡Bienvenido, <?php echo htmlspecialchars($nombre_doctor); ?>!
                            </h2>

                            <p class="mb-0">
                                <i class="fas fa-stethoscope me-2"></i>
                                <strong>Especialidad:</strong>
                                <span style="color: #ffd700; font-weight: 700; background: rgba(255, 215, 0, 0.15); padding: 2px 12px; border-radius: 20px; border: 1px solid rgba(255, 215, 0, 0.3);">
                                    <?php echo htmlspecialchars($especialidad_doctor); ?>
                                </span>
                                <span class="mx-2">|</span>
                                <i class="fas fa-calendar-day me-2"></i>
                                <?php echo fecha_espanol(); ?>
                            </p>
                        </div>
                    </div>

                    <div class="dropdown position-relative" style="z-index: 1050;">
                        <button id="btn-campana" class="btn btn-light border-0 shadow-sm rounded-circle position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 45px; height: 45px;">
                            <i id="icono-campana" class="fas fa-bell <?php echo ($num_notificaciones > 0) ? 'bell-shake' : 'text-muted'; ?>" style="font-size: 1.2rem; <?php echo ($num_notificaciones > 0) ? 'color: #f59e0b;' : ''; ?>"></i>
                            <?php if ($num_notificaciones > 0): ?>
                                <span id="badge-notificaciones" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                                    <?php echo $num_notificaciones; ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        
                        <ul class="dropdown-menu dropdown-menu-end notif-dropdown p-0" style="width: 350px; z-index: 9999;">
                            <li class="notif-header">
                                <h6 class="m-0 fw-bold d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-bell me-2"></i> Notificaciones</span>
                                    <span id="texto-nuevas" class="badge bg-white text-primary rounded-pill"><?php echo $num_notificaciones; ?> nuevas</span>
                                </h6>
                            </li>
                            
                            <div class="notif-scroll" style="max-height: 380px; overflow-y: auto;">
                                <?php if ($num_notificaciones > 0): ?>
                                    <?php foreach ($notificaciones_recientes as $notif): ?>
                                        <a class="notif-item" href="notificaciones.php">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 35px; height: 35px;">
                                                    <i class="fas fa-check text-success" style="font-size: 0.9rem;"></i>
                                                </div>
                                                <div>
                                                    <div class="notif-text"><?php echo htmlspecialchars($notif['mensaje']); ?></div>
                                                    <div class="notif-meta">
                                                        <span><i class="far fa-clock me-1"></i><?php echo date('d/m/Y H:i', strtotime($notif['fecha'])); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li class="p-5 text-center text-muted">
                                        <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                            <i class="fas fa-check-double fa-2x opacity-50"></i>
                                        </div>
                                        <h6 class="fw-bold text-dark">Todo al día</h6>
                                        <p class="small m-0">No tienes notificaciones nuevas por ahora.</p>
                                    </li>
                                <?php endif; ?>
                            </div>
                            
                            <li class="border-top">
                                <a href="notificaciones.php" class="dropdown-item p-3 text-center text-primary fw-bold" style="transition: all 0.2s; font-size: 0.85rem; background: #f8fafc; border-radius: 0 0 16px 16px;">
                                    Ver todas las notificaciones <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 stat-card">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                        <i class="fas fa-prescription text-primary fa-2x"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0 display-6"><?php echo $total_recetas; ?></h5>
                        <small class="text-muted">Total de recetas</small>
                    </div>
                    <div class="ms-auto text-primary opacity-25">
                        <i class="fas fa-prescription fa-3x"></i>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0">
                    <div class="progress" style="height: 4px;">
                        <div class="progress-bar bg-primary" role="progressbar"
                             style="width: <?php echo min($total_recetas * 10, 100); ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 stat-card">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                        <i class="fas fa-clock text-warning fa-2x"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0 display-6"><?php echo $recetas_pendientes; ?></h5>
                        <small class="text-muted">Pendientes</small>
                    </div>
                    <div class="ms-auto text-warning opacity-25">
                        <i class="fas fa-clock fa-3x"></i>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0">
                    <div class="progress" style="height: 4px;">
                        <div class="progress-bar bg-warning" role="progressbar"
                             style="width: <?php echo min($recetas_pendientes * 20, 100); ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 stat-card">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                        <i class="fas fa-users text-success fa-2x"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0 display-6"><?php echo $pacientes_atendidos; ?></h5>
                        <small class="text-muted">Pacientes atendidos</small>
                    </div>
                    <div class="ms-auto text-success opacity-25">
                        <i class="fas fa-users fa-3x"></i>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0">
                    <div class="progress" style="height: 4px;">
                        <div class="progress-bar bg-success" role="progressbar"
                             style="width: <?php echo min($pacientes_atendidos * 20, 100); ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-lg rounded-4 glass-card">
                <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex justify-content-between align-items-center flex-wrap">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-prescription text-primary me-2"></i>
                        Mis Últimas Recetas
                        <span class="badge bg-primary-subtle text-primary rounded-pill ms-2" style="font-size: 0.75rem;">
                            <?php echo $total_registros; ?> total
                        </span>
                    </h5>

                    <a href="historial_recetas.php" class="btn btn-outline-primary rounded-pill px-3 btn-sm">
                        Ver todas <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>

                <div class="card-body p-4 pt-2">
                    <?php if ($ultimas_recetas && $ultimas_recetas->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="bg-light rounded-4">
                                    <tr>
                                        <th>#</th>
                                        <th>Paciente</th>
                                        <th>Fecha</th>
                                        <th>Estado</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($receta = $ultimas_recetas->fetch_assoc()): ?>
                                        <?php
                                        $badge_class = match ($receta['estado']) {
                                            'pendiente' => 'warning',
                                            'aprobada' => 'success',
                                            'entregada' => 'info',
                                            'rechazada' => 'danger',
                                            default => 'secondary'
                                        };

                                        $icon = match ($receta['estado']) {
                                            'pendiente' => 'clock',
                                            'aprobada' => 'check-circle',
                                            'entregada' => 'truck',
                                            'rechazada' => 'times-circle',
                                            default => 'circle'
                                        };
                                        ?>

                                        <tr>
                                            <td>
                                                <span class="badge bg-secondary-subtle text-secondary rounded-pill">
                                                    #<?php echo $receta['id']; ?>
                                                </span>
                                            </td>

                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm me-2"
                                                         style="background: <?php echo '#' . substr(md5($receta['paciente_nombre']), 0, 6); ?>;">
                                                        <span><?php echo strtoupper(substr($receta['paciente_nombre'], 0, 2)); ?></span>
                                                    </div>
                                                    <?php echo htmlspecialchars($receta['paciente_nombre']); ?>
                                                </div>
                                            </td>

                                            <td><?php echo date('d/m/Y', strtotime($receta['fecha_receta'])); ?></td>

                                            <td>
                                                <span class="badge bg-<?php echo $badge_class; ?>-subtle text-<?php echo $badge_class; ?>-emphasis rounded-pill px-3 py-2">
                                                    <i class="fas fa-<?php echo $icon; ?> me-1"></i>
                                                    <?php echo ucfirst($receta['estado']); ?>
                                                </span>
                                            </td>

                                            <td class="text-end">
                                                <a href="detalle_receta.php?id=<?php echo $receta['id']; ?>"
                                                   class="btn btn-sm btn-info rounded-pill px-3">
                                                    <i class="fas fa-eye"></i> Ver
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($total_paginas > 1): ?>
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center mb-0">
                                    <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link mx-1" href="?pagina=<?php echo $pagina - 1; ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>

                                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                        <li class="page-item <?php echo $pagina == $i ? 'active' : ''; ?>">
                                            <a class="page-link mx-1" href="?pagina=<?php echo $i; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <li class="page-item <?php echo $pagina >= $total_paginas ? 'disabled' : ''; ?>">
                                        <a class="page-link mx-1" href="?pagina=<?php echo $pagina + 1; ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-prescription fa-3x mb-3 d-block"></i>
                            <h5>No tienes recetas registradas aún</h5>
                            <p class="small">Comienza creando una nueva receta desde el menú lateral.</p>
                            <a href="crear_receta.php?paciente_id=0" class="btn btn-primary rounded-pill px-4">
                                <i class="fas fa-plus me-2"></i> Crear Receta
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnCampana = document.getElementById('btn-campana');
    
    if (btnCampana) {
        btnCampana.addEventListener('click', function () {
            
            const badge = document.getElementById('badge-notificaciones');
            const icono = document.getElementById('icono-campana');
            const textoNuevas = document.getElementById('texto-nuevas');

            if (badge) badge.style.display = 'none';
            if (icono) {
                icono.classList.remove('bell-shake');
                icono.style.color = '#6c757d'; 
            }
            if (textoNuevas) textoNuevas.innerText = '0 nuevas';

            fetch('dashboard.php?marcar_leidas=1')
                .catch(error => console.log('Error al actualizar:', error));
        });
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>