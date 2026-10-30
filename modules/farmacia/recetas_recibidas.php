<?php
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('America/Managua');
require_once '../../config/database.php';

$titulo_pagina = "Recetas Recibidas";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

if ($_SESSION['tipo_usuario'] != 'farmacia') {
    header("Location: /login.php");
    exit();
}

$mensaje = '';
$tipo_mensaje = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && isset($_POST['receta_id'])) {
    $receta_id = intval($_POST['receta_id']);
    $accion = $_POST['accion'];
    $mensaje_admin = trim($_POST['mensaje_admin'] ?? '');
    $admin_id = $_SESSION['usuario_id'];

    $sql_check = "SELECT r.*, u.nombre AS doctor_nombre, u.id AS doctor_id, u.especialidad 
                  FROM recetas r
                  JOIN usuarios u ON r.doctor_id = u.id
                  WHERE r.id = ? AND r.estado = 'pendiente'";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $receta_id);
    $stmt_check->execute();
    $receta = $stmt_check->get_result()->fetch_assoc();

    if (!$receta) {
        $mensaje = "Receta no encontrada o ya procesada.";
        $tipo_mensaje = "danger";
    } else {
        $nuevo_estado = ($accion == 'aprobar') ? 'aprobada' : 'rechazada';
        $tipo_notificacion = $nuevo_estado;
        $accion_recomendada = ($accion == 'aprobar') 
            ? 'Receta aprobada. Puedes proceder con la entrega.' 
            : 'Receta rechazada. Consulta el motivo con el administrador.';

        $conn->begin_transaction();

        try {
            if ($accion == 'aprobar') {
                $sql_detalles = "SELECT id, medicamento_id, cantidad FROM receta_detalles WHERE receta_id = ?";
                $stmt_detalles = $conn->prepare($sql_detalles);
                if (!$stmt_detalles) throw new Exception("Error al preparar medicamentos: " . $conn->error);
                $stmt_detalles->bind_param("i", $receta_id);
                $stmt_detalles->execute();
                $result_detalles = $stmt_detalles->get_result();

                if ($result_detalles->num_rows == 0) {
                    throw new Exception("La receta no tiene medicamentos asociados.");
                }

                while ($detalle = $result_detalles->fetch_assoc()) {
                    $detalle_id = $detalle['id'];
                    $medicamento_id = $detalle['medicamento_id'];
                    $cantidad_solicitada = $detalle['cantidad'];

                    $sql_lote = "SELECT id, cantidad, fecha_vencimiento FROM lotes 
                                 WHERE medicamento_id = ? AND cantidad >= ? AND fecha_vencimiento >= CURDATE() 
                                 ORDER BY fecha_vencimiento ASC LIMIT 1";
                    $stmt_lote = $conn->prepare($sql_lote);
                    if (!$stmt_lote) throw new Exception("Error al buscar lote: " . $conn->error);
                    $stmt_lote->bind_param("ii", $medicamento_id, $cantidad_solicitada);
                    $stmt_lote->execute();
                    $lote = $stmt_lote->get_result()->fetch_assoc();
                    $stmt_lote->close();

                    if (!$lote) {
                        throw new Exception("No hay suficiente medicamento disponible para el medicamento #$medicamento_id.");
                    }

                    $sql_asignar = "UPDATE receta_detalles SET lote_id = ? WHERE id = ?";
                    $stmt_asignar = $conn->prepare($sql_asignar);
                    if (!$stmt_asignar) throw new Exception("Error al preparar asignación de lote: " . $conn->error);
                    $stmt_asignar->bind_param("ii", $lote['id'], $detalle_id);
                    if (!$stmt_asignar->execute()) throw new Exception("Error al asignar lote: " . $stmt_asignar->error);
                    $stmt_asignar->close();
                }
                $stmt_detalles->close();
            }
            $sql_update = "UPDATE recetas SET estado = ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            if (!$stmt_update) throw new Exception("Error al preparar actualización: " . $conn->error);
            $stmt_update->bind_param("si", $nuevo_estado, $receta_id);
            if (!$stmt_update->execute()) throw new Exception("Error al actualizar receta: " . $stmt_update->error);
            $stmt_update->close();
            $mensaje_notif = ($accion == 'aprobar')
                ? "Receta #$receta_id aprobada por el administrador."
                : "Receta #$receta_id rechazada por el administrador.";

            if (!empty($mensaje_admin)) {
                $mensaje_notif .= " Mensaje: " . $mensaje_admin;
            }

            $sql_notif = "INSERT INTO notificaciones (doctor_id, receta_id, tipo, mensaje, accion_recomendada, fecha, leido) 
                          VALUES (?, ?, ?, ?, ?, NOW(), 0)";
            $stmt_notif = $conn->prepare($sql_notif);
            if (!$stmt_notif) throw new Exception("Error al preparar notificación: " . $conn->error);
            $stmt_notif->bind_param("iisss", $receta['doctor_id'], $receta_id, $tipo_notificacion, $mensaje_notif, $accion_recomendada);
            if (!$stmt_notif->execute()) throw new Exception("Error al crear notificación: " . $stmt_notif->error);
            $stmt_notif->close();

            $conn->commit();

            $mensaje = ($accion == 'aprobar')
                ? "Receta aprobada correctamente. Lote asignado y notificación enviada al médico."
                : "Receta rechazada correctamente. Notificación enviada al médico.";
            $tipo_mensaje = "success";

            echo '<script>
                setTimeout(function() {
                    window.location.href = "recetas_recibidas.php";
                }, 1500);
            </script>';

        } catch (Exception $e) {
            $conn->rollback();
            $mensaje = "Error al procesar la receta: " . $e->getMessage();
            $tipo_mensaje = "danger";
        }
    }
}

$estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : 'pendiente';
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

$where = [];
$params = [];
$types = "";

if ($estado_filtro != 'todos') {
    $where[] = "r.estado = ?";
    $params[] = $estado_filtro;
    $types .= "s";
}
if (!empty($buscar)) {
    $where[] = "(CONCAT(p.nombre, ' ', p.apellido) LIKE ? OR p.telefono LIKE ?)";
    $busqueda = "%$buscar%";
    $params[] = $busqueda;
    $params[] = $busqueda;
    $types .= "ss";
}
$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$por_pagina = 6;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_actual - 1) * $por_pagina;

$sql_count = "SELECT COUNT(*) as total FROM recetas r JOIN pacientes p ON r.paciente_id = p.id $where_clause";
$stmt_count = $conn->prepare($sql_count);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_registros = $stmt_count->get_result()->fetch_assoc()['total'];
$stmt_count->close();
$total_paginas = ceil($total_registros / $por_pagina);

$sql = "SELECT r.*, 
               CONCAT(p.nombre, ' ', p.apellido) AS paciente_nombre,
               p.edad, p.sexo, p.telefono,
               u.nombre AS doctor_nombre
        FROM recetas r
        JOIN pacientes p ON r.paciente_id = p.id
        JOIN usuarios u ON r.doctor_id = u.id
        $where_clause
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?";

$params_pag = array_merge($params, [$por_pagina, $offset]);
$types_pag = $types . "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types_pag, ...$params_pag);
$stmt->execute();
$recetas = $stmt->get_result();

$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado = 'aprobada' THEN 1 ELSE 0 END) as aprobadas,
        SUM(CASE WHEN estado = 'entregada' THEN 1 ELSE 0 END) as entregadas,
        SUM(CASE WHEN estado = 'rechazada' THEN 1 ELSE 0 END) as rechazadas
    FROM recetas
")->fetch_assoc();
?>

<style>
    .main-content { padding-top: 0 !important; }
    .container-fluid.px-4 { margin-top: -15px; }

    :root {
        --fr-primary: #8b5cf6;
        --fr-primary-dark: #6d28d9;
        --fr-primary-light: #f3e8ff;
    }
    .fr-hero {
        background: linear-gradient(135deg, #4c1d95 0%, #7c3aed 55%, #a78bfa 100%);
        border-radius: 24px;
        padding: 32px 36px;
        color: #fff;
        position: relative;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(124, 58, 237, 0.25);
        margin-bottom: 24px;
    }
    .fr-hero::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background-image: radial-gradient(circle, rgba(255,255,255,0.12) 1px, transparent 1px);
        background-size: 20px 20px;
        opacity: 0.6;
    }
    .fr-hero::after {
        content: '\f484';
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        position: absolute;
        right: -20px; bottom: -40px;
        font-size: 200px;
        opacity: 0.08;
        transform: rotate(-10deg);
    }
    .fr-hero-content { position: relative; z-index: 1; }
    .fr-hero-icon {
        width: 68px; height: 68px;
        background: rgba(255,255,255,0.2);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.7rem;
        flex-shrink: 0;
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    }
    .fr-hero h2 { font-size: 1.7rem; font-weight: 700; margin: 0 0 4px 0; }
    .fr-hero p { margin: 0; opacity: 0.9; font-size: 0.92rem; }
    .fr-kpi {
        background: #fff;
        border-radius: 18px;
        padding: 18px 20px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05);
        transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        height: 100%;
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .fr-kpi::before {
        content: '';
        position: absolute;
        top: 0; left: 0;
        width: 4px; height: 100%;
        background: var(--kpi-color, #8b5cf6);
        transition: width 0.35s;
    }
    .fr-kpi:hover { transform: translateY(-5px); box-shadow: 0 14px 32px rgba(15, 23, 42, 0.1); }
    .fr-kpi:hover::before { width: 8px; }
    .fr-kpi-icon {
        width: 48px; height: 48px;
        border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem; color: #fff;
        flex-shrink: 0;
        box-shadow: 0 6px 16px rgba(0,0,0,0.15);
        transition: transform 0.35s;
    }
    .fr-kpi:hover .fr-kpi-icon { transform: scale(1.1) rotate(-5deg); }
    .fr-kpi-label {
        font-size: 0.68rem; font-weight: 700; color: #94a3b8;
        text-transform: uppercase; letter-spacing: 0.8px;
        margin-bottom: 3px; display: block;
    }
    .fr-kpi-value {
        font-size: 1.6rem; font-weight: 800; color: #0f172a;
        line-height: 1; margin: 0;
    }
    .fr-filter-card {
        background: #fff;
        border-radius: 20px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 6px 22px rgba(15, 23, 42, 0.05);
        overflow: hidden;
        margin-bottom: 24px;
    }
    .fr-filter-header {
        padding: 18px 24px;
        background: linear-gradient(135deg, #f3e8ff, #ede9fe);
        border-bottom: 1px solid #ddd6fe;
    }
    .fr-filter-header h6 {
        margin: 0; font-weight: 700; color: var(--fr-primary-dark);
        font-size: 0.9rem; display: flex; align-items: center; gap: 10px;
    }
    .fr-filter-header h6 i {
        width: 30px; height: 30px;
        background: #fff;
        color: var(--fr-primary);
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.85rem;
        box-shadow: 0 4px 12px rgba(139,92,246,0.15);
    }
    .fr-filter-body { padding: 20px 24px; }
    .fr-filter-body .form-label {
        font-size: 0.78rem; font-weight: 600; color: #475569;
        margin-bottom: 6px;
    }
    .fr-filter-body .form-control,
    .fr-filter-body .form-select {
        border: 1px solid #e2e8f0;
        padding: 10px 14px;
        font-size: 0.88rem;
        background: #fafbfc;
        transition: all 0.2s;
    }
    .fr-filter-body .form-control:focus,
    .fr-filter-body .form-select:focus {
        border-color: var(--fr-primary);
        box-shadow: 0 0 0 4px rgba(139,92,246,0.12);
        background: #fff;
    }
    .fr-btn-filter {
        background: linear-gradient(135deg, var(--fr-primary-dark), var(--fr-primary));
        color: #fff; border: none;
        padding: 10px 24px;
        border-radius: 50px;
        font-weight: 700; font-size: 0.85rem;
        transition: all 0.3s;
        width: 100%;
        box-shadow: 0 6px 16px rgba(139,92,246,0.28);
    }
    .fr-btn-filter:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 24px rgba(139,92,246,0.4);
        color: #fff;
    }
    .fr-table-card {
        background: #fff;
        border-radius: 20px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 6px 22px rgba(15, 23, 42, 0.05);
        overflow: hidden;
    }
    .fr-table-header {
        padding: 20px 24px;
        background: linear-gradient(180deg, #fafbfc, #f8fafc);
        border-bottom: 1px solid #f1f5f9;
        display: flex; justify-content: space-between; align-items: center;
        flex-wrap: wrap; gap: 12px;
    }
    .fr-table-header h6 { margin: 0; font-weight: 700; color: #0f172a; font-size: 0.95rem; }
    .fr-count {
        background: var(--fr-primary); color: #fff;
        padding: 6px 14px; border-radius: 20px;
        font-size: 0.75rem; font-weight: 700;
    }

    .fr-table { margin: 0; font-size: 0.87rem; }
    .fr-table thead th {
        background: #f8fafc;
        font-size: 0.7rem; text-transform: uppercase;
        letter-spacing: 0.8px; font-weight: 700;
        color: #64748b; padding: 14px 18px;
        border-bottom: 1px solid #e2e8f0;
    }
    .fr-table tbody td {
        padding: 14px 18px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }
    .fr-table tbody tr { transition: all 0.18s; }
    .fr-table tbody tr:hover {
        background: linear-gradient(90deg, rgba(139,92,246,0.04), transparent);
    }

    .fr-avatar {
        width: 38px; height: 38px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 700; font-size: 0.82rem;
        flex-shrink: 0;
        box-shadow: 0 4px 10px rgba(0,0,0,0.12);
    }

    .fr-badge {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 5px 14px; border-radius: 20px;
        font-size: 0.72rem; font-weight: 700;
    }
    .fr-badge-pendiente  { background: #fef3c7; color: #92400e; border: 2px solid #f59e0b; }
    .fr-badge-aprobada   { background: #d1fae5; color: #065f46; border: 2px solid #10b981; }
    .fr-badge-entregada  { background: #dbeafe; color: #1e40af; border: 2px solid #3b82f6; }
    .fr-badge-rechazada  { background: #fee2e2; color: #991b1b; border: 2px solid #ef4444; }

    .fr-action-btn {
        padding: 6px 14px;
        border-radius: 50px;
        border: none;
        font-size: 0.72rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .fr-btn-approve { background: linear-gradient(135deg, #059669, #10b981); color: #fff; }
    .fr-btn-approve:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(16,185,129,0.4); color: #fff; }
    .fr-btn-reject { background: linear-gradient(135deg, #dc2626, #ef4444); color: #fff; }
    .fr-btn-reject:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(220,38,38,0.4); color: #fff; }
    .fr-btn-deliver { background: linear-gradient(135deg, #0891b2, #06b6d4); color: #fff; }
    .fr-btn-deliver:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(8,145,178,0.4); color: #fff; }

    .fr-alert {
        border: none; border-radius: 14px;
        padding: 14px 18px;
        display: flex; align-items: center; gap: 10px;
        font-weight: 500;
        box-shadow: 0 4px 14px rgba(0,0,0,0.06);
        margin-bottom: 20px;
    }
    .fr-alert-success { background: linear-gradient(135deg,#ecfdf5,#d1fae5); color: #065f46; }
    .fr-alert-danger  { background: linear-gradient(135deg,#fef2f2,#fee2e2); color: #991b1b; }

    .pagination { gap: 6px; }
    .page-item .page-link {
        border-radius: 50%;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #e9ecef;
        color: #6d28d9;
        font-weight: 700;
        transition: all 0.2s;
    }
    .page-item.active .page-link {
        background: linear-gradient(135deg, #4c1d95, #7c3aed);
        color: white;
        border-color: transparent;
        box-shadow: 0 4px 10px rgba(124,58,237,0.3);
    }
    .page-item .page-link:hover {
        background: #f8f9fa;
        border-color: #7c3aed;
    }

    .fade-in { animation: fadeUp 0.5s ease forwards; }
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(20px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .stagger-1 { animation: fadeUp 0.5s ease 0.05s backwards; }
    .stagger-2 { animation: fadeUp 0.5s ease 0.10s backwards; }
    .stagger-3 { animation: fadeUp 0.5s ease 0.15s backwards; }
    .stagger-4 { animation: fadeUp 0.5s ease 0.20s backwards; }
    .stagger-5 { animation: fadeUp 0.5s ease 0.25s backwards; }
    .stagger-6 { animation: fadeUp 0.5s ease 0.30s backwards; }

    @media (max-width: 768px) {
        .fr-hero { padding: 22px; }
        .fr-hero h2 { font-size: 1.3rem; }
        .fr-kpi { padding: 14px; }
        .fr-kpi-value { font-size: 1.3rem; }
    }
</style>

<div class="container-fluid px-4 fade-in">
    <div class="fr-hero">
        <div class="fr-hero-content d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center">
                <div class="fr-hero-icon me-3">
                    <i class="fas fa-prescription"></i>
                </div>
                <div>
                    <h2>Control de Recetas Médicas</h2>
                    <p><i class="fas fa-clinic-medical me-2"></i>Gestiona las recetas recibidas de los doctores</p>
                </div>
            </div>
            <a href="dashboard.php" class="btn btn-light rounded-pill px-4 fw-semibold">
                <i class="fas fa-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>
    <?php if ($mensaje): ?>
        <div class="fr-alert fr-alert-<?php echo $tipo_mensaje === 'success' ? 'success' : 'danger'; ?>">
            <i class="fas fa-<?php echo $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            <div><?php echo $mensaje; ?></div>
        </div>
    <?php endif; ?>
    <div class="row g-3 mb-4">
        <div class="col-xl-2 col-md-4 col-6 stagger-1">
            <div class="fr-kpi" style="--kpi-color: #7c3aed;">
                <div class="fr-kpi-icon" style="background: linear-gradient(135deg, #4c1d95, #7c3aed);">
                    <i class="fas fa-prescription"></i>
                </div>
                <div>
                    <span class="fr-kpi-label">Total</span>
                    <div class="fr-kpi-value"><?php echo $stats['total']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 stagger-2">
            <div class="fr-kpi" style="--kpi-color: #d97706;">
                <div class="fr-kpi-icon" style="background: linear-gradient(135deg, #b45309, #f59e0b);">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <span class="fr-kpi-label">Pendientes</span>
                    <div class="fr-kpi-value"><?php echo $stats['pendientes']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 stagger-3">
            <div class="fr-kpi" style="--kpi-color: #059669;">
                <div class="fr-kpi-icon" style="background: linear-gradient(135deg, #065f46, #10b981);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <span class="fr-kpi-label">Aprobadas</span>
                    <div class="fr-kpi-value"><?php echo $stats['aprobadas']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 stagger-4">
            <div class="fr-kpi" style="--kpi-color: #0891b2;">
                <div class="fr-kpi-icon" style="background: linear-gradient(135deg, #0e7490, #06b6d4);">
                    <i class="fas fa-truck"></i>
                </div>
                <div>
                    <span class="fr-kpi-label">Entregadas</span>
                    <div class="fr-kpi-value"><?php echo $stats['entregadas']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 stagger-5">
            <div class="fr-kpi" style="--kpi-color: #dc2626;">
                <div class="fr-kpi-icon" style="background: linear-gradient(135deg, #7f1d1d, #dc2626);">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div>
                    <span class="fr-kpi-label">Rechazadas</span>
                    <div class="fr-kpi-value"><?php echo $stats['rechazadas']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 stagger-6">
            <div class="fr-kpi" style="--kpi-color: #3b82f6;">
                <div class="fr-kpi-icon" style="background: linear-gradient(135deg, #1e40af, #3b82f6);">
                    <i class="fas fa-percent"></i>
                </div>
                <div>
                    <span class="fr-kpi-label">Efectividad</span>
                    <div class="fr-kpi-value">
                        <?php 
                        $efectivas = $stats['aprobadas'] + $stats['entregadas'];
                        $tasa = $stats['total'] > 0 ? round(($efectivas / $stats['total']) * 100) : 0;
                        echo $tasa . '%';
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="fr-filter-card">
        <div class="fr-filter-header">
            <h6><i class="fas fa-sliders-h"></i> Filtros de Búsqueda</h6>
        </div>
        <div class="fr-filter-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select rounded-pill">
                        <option value="todos" <?php echo $estado_filtro == 'todos' ? 'selected' : ''; ?>>Todos</option>
                        <option value="pendiente" <?php echo $estado_filtro == 'pendiente' ? 'selected' : ''; ?>>Pendientes</option>
                        <option value="aprobada" <?php echo $estado_filtro == 'aprobada' ? 'selected' : ''; ?>>Aprobadas</option>
                        <option value="entregada" <?php echo $estado_filtro == 'entregada' ? 'selected' : ''; ?>>Entregadas</option>
                        <option value="rechazada" <?php echo $estado_filtro == 'rechazada' ? 'selected' : ''; ?>>Rechazadas</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Buscar Paciente</label>
                    <input type="text" name="buscar" class="form-control rounded-pill"
                           placeholder="Nombre, apellido o teléfono..."
                           value="<?php echo htmlspecialchars($buscar); ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="fr-btn-filter">
                        <i class="fas fa-search me-2"></i> Aplicar Filtros
                    </button>
                </div>
            </form>
        </div>
    </div>
    <div class="fr-table-card">
        <div class="fr-table-header">
            <h6><i class="fas fa-list me-2" style="color: var(--fr-primary);"></i>Recetas Recibidas</h6>
            <span class="fr-count"><?php echo $total_registros; ?> registros</span>
        </div>
        <div class="table-responsive">
            <table class="table fr-table">
                <thead>
                    <tr>
                        <th style="width: 6%;">ID</th>
                        <th style="width: 30%;">Paciente</th>
                        <th style="width: 22%;">Doctor</th>
                        <th style="width: 17%;">Estado</th>
                        <th style="width: 25%;" class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($recetas->num_rows > 0): ?>
                    <?php while ($receta = $recetas->fetch_assoc()): 
                        $estado = $receta['estado'];
                        $icon = match($estado) {
                            'pendiente' => 'clock',
                            'aprobada' => 'check-circle',
                            'entregada' => 'truck',
                            'rechazada' => 'times-circle',
                            default => 'circle'
                        };
                        
                        $doctor_nombre = trim($receta['doctor_nombre'] ?? '');
                        if (empty($doctor_nombre)) {
                            $doctor_nombre = 'Dr. sin nombre';
                        }
                        
                        $inicial_pac = strtoupper(substr($receta['paciente_nombre'], 0, 2));
                        $inicial_doc = strtoupper(substr($doctor_nombre, 0, 2));
                    ?>
                        <tr>
                            <td>
                                <span class="badge bg-light text-secondary rounded-pill px-2 py-1">
                                    #<?php echo $receta['id']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="fr-avatar" style="background: <?php echo '#' . substr(md5($receta['paciente_nombre']), 0, 6); ?>;">
                                        <?php echo $inicial_pac; ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($receta['paciente_nombre']); ?></div>
                                        <small class="text-muted">
                                            <?php echo $receta['edad']; ?> años · <?php echo $receta['sexo'] == 'M' ? 'M' : 'F'; ?>
                                        </small>
                                        <?php if (!empty($receta['telefono'])): ?>
                                            <div><small class="text-muted"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($receta['telefono']); ?></small></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="fr-avatar" style="background: #6d28d9; font-size: 0.75rem;">
                                        <i class="fas fa-user-md"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-dark"><?php echo htmlspecialchars($doctor_nombre); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="fr-badge fr-badge-<?php echo $estado; ?>">
                                    <i class="fas fa-<?php echo $icon; ?>"></i>
                                    <?php echo ucfirst($estado); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <?php if ($receta['estado'] == 'pendiente'): ?>
                                    <button class="fr-action-btn fr-btn-approve me-1" 
                                            onclick="abrirModal(<?php echo $receta['id']; ?>, 'aprobar', '<?php echo addslashes($receta['paciente_nombre']); ?>', '<?php echo addslashes($doctor_nombre); ?>')">
                                        <i class="fas fa-check"></i> Aprobar
                                    </button>
                                    <button class="fr-action-btn fr-btn-reject" 
                                            onclick="abrirModal(<?php echo $receta['id']; ?>, 'rechazar', '<?php echo addslashes($receta['paciente_nombre']); ?>', '<?php echo addslashes($doctor_nombre); ?>')">
                                        <i class="fas fa-times"></i> Rechazar
                                    </button>
                                <?php elseif ($receta['estado'] == 'aprobada'): ?>
                                    <a href="entrega_medicamentos.php?receta=<?php echo $receta['id']; ?>" 
                                       class="fr-action-btn fr-btn-deliver">
                                        <i class="fas fa-truck"></i> Entregar
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="fas fa-prescription fa-3x mb-3 d-block opacity-50"></i>
                            <h6 class="fw-bold">No hay recetas que coincidan con los filtros</h6>
                            <p class="small mb-0">Cambia los filtros para ver más resultados</p>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_paginas > 1): ?>
        <div class="card-footer bg-white border-0 py-3">
            <nav>
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?php echo $pagina_actual <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?estado=<?php echo urlencode($estado_filtro); ?>&buscar=<?php echo urlencode($buscar); ?>&pagina=<?php echo $pagina_actual - 1; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <li class="page-item <?php echo $pagina_actual == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?estado=<?php echo urlencode($estado_filtro); ?>&buscar=<?php echo urlencode($buscar); ?>&pagina=<?php echo $i; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $pagina_actual >= $total_paginas ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?estado=<?php echo urlencode($estado_filtro); ?>&buscar=<?php echo urlencode($buscar); ?>&pagina=<?php echo $pagina_actual + 1; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>
<div class="modal fade" id="modalAccion" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius: 20px; overflow: hidden; border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.25);">
            <div class="modal-header" style="background: linear-gradient(135deg, #4c1d95, #7c3aed); color: #fff; border: none; padding: 20px 26px;">
                <h5 class="modal-title fw-bold" id="modalAccionTitle">
                    <i class="fas fa-info-circle me-2"></i> Confirmar acción
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="formAccion">
                <div class="modal-body p-4" style="background: #fafbfc;">
                    <input type="hidden" name="receta_id" id="receta_id">
                    <input type="hidden" name="accion" id="accion">

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="p-3 rounded-4" style="background: #fff; border: 1px solid #e5e7eb;">
                                <h6 style="color: #7c3aed; font-size: 0.85rem;">
                                    <i class="fas fa-user me-2"></i>Paciente
                                </h6>
                                <hr style="margin: 8px 0;">
                                <p class="mb-1 small"><strong>Nombre:</strong> <span id="modalPaciente"></span></p>
                                <p class="mb-0 small"><strong>Receta:</strong> <span id="modalRecetaId"></span></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded-4" style="background: #fff; border: 1px solid #e5e7eb;">
                                <h6 style="color: #10b981; font-size: 0.85rem;">
                                    <i class="fas fa-user-md me-2"></i>Doctor
                                </h6>
                                <hr style="margin: 8px 0;">
                                <p class="mb-1 small"><strong>Nombre:</strong> <span id="modalDoctor"></span></p>
                            </div>
                        </div>
                    </div>

                    <p id="modalMensajeConfirmacion" class="mb-3 fw-semibold text-center"></p>

                    <div class="mb-0">
                        <label class="form-label fw-semibold small">Mensaje para el médico (opcional)</label>
                        <textarea name="mensaje_admin" id="mensaje_admin" class="form-control rounded-4" rows="3"
                                  placeholder="Ej: La receta ha sido aprobada. Puede proceder con la entrega."></textarea>
                        <small class="text-muted">Este mensaje aparecerá en las notificaciones del médico.</small>
                    </div>
                </div>
                <div class="modal-footer" style="background: #fff; border: none; padding: 16px 26px;">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4" id="btnConfirmar" style="background: linear-gradient(135deg, #6d28d9, #8b5cf6); border: none;">
                        <i class="fas fa-check me-1"></i> Confirmar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirModal(id, accion, paciente, doctor) {
    document.getElementById('receta_id').value = id;
    document.getElementById('accion').value = accion;
    document.getElementById('modalPaciente').textContent = paciente;
    document.getElementById('modalDoctor').textContent = doctor || 'Dr. sin nombre';
    document.getElementById('modalRecetaId').textContent = '#' + id;

    const titulo = accion === 'aprobar' ? 'Aprobar Receta' : 'Rechazar Receta';
    const icono = accion === 'aprobar' ? 'fa-check-circle' : 'fa-times-circle';
    const mensaje = accion === 'aprobar' 
        ? `¿Confirmas la <strong>aprobación</strong> de la receta de <strong>${paciente}</strong>?`
        : `¿Confirmas el <strong>rechazo</strong> de la receta de <strong>${paciente}</strong>?`;

    document.getElementById('modalAccionTitle').innerHTML = `<i class="fas ${icono} me-2"></i> ${titulo}`;
    document.getElementById('modalMensajeConfirmacion').innerHTML = mensaje;

    const btn = document.getElementById('btnConfirmar');
    if (accion === 'aprobar') {
        btn.style.background = 'linear-gradient(135deg, #059669, #10b981)';
        btn.innerHTML = '<i class="fas fa-check me-1"></i> Aprobar';
    } else {
        btn.style.background = 'linear-gradient(135deg, #dc2626, #ef4444)';
        btn.innerHTML = '<i class="fas fa-times me-1"></i> Rechazar';
    }

    new bootstrap.Modal(document.getElementById('modalAccion')).show();
}
</script>

<?php require_once '../../includes/footer.php'; ?>