<?php
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('America/Managua');
require_once '../../config/database.php';

$titulo_pagina = "Entrega de Medicamentos";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

if ($_SESSION['tipo_usuario'] != 'farmacia') {
    header("Location: /login.php");
    exit();
}
$conn->query("
    CREATE TABLE IF NOT EXISTS entregas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        receta_id INT NOT NULL,
        admin_id INT NOT NULL,
        observaciones TEXT NULL,
        fecha_entrega DATETIME NOT NULL,
        FOREIGN KEY (receta_id) REFERENCES recetas(id) ON DELETE CASCADE,
        FOREIGN KEY (admin_id) REFERENCES usuarios(id) ON DELETE CASCADE
    )
");

$mensaje = '';
$tipo_mensaje = '';
$receta = null;
$lista_detalles = [];
if (isset($_GET['receta']) && is_numeric($_GET['receta'])) {
    $receta_id = intval($_GET['receta']);

    $sql = "SELECT r.*, 
               CONCAT(p.nombre, ' ', p.apellido) AS paciente_nombre,
               p.telefono, p.edad, p.sexo, p.tiene_whatsapp, p.nombre_tutor, p.telefono_tutor,
               u.nombre AS doctor_nombre,
               es.nombre AS doctor_especialidad
        FROM recetas r
        JOIN pacientes p ON r.paciente_id = p.id
        JOIN usuarios u ON r.doctor_id = u.id
        LEFT JOIN especialidades es ON u.especialidad_id = es.id
        WHERE r.id = ? AND r.estado = 'aprobada'";
    $stmt = $conn->prepare($sql);
    if (!$stmt) die("Error SQL: " . $conn->error);
    $stmt->bind_param("i", $receta_id);
    $stmt->execute();
    $receta = $stmt->get_result()->fetch_assoc();

    if (!$receta) {
        $mensaje = "Receta no encontrada o no está aprobada.";
        $tipo_mensaje = "danger";
    } else {
        $sql_detalles = "SELECT rd.cantidad, 
                        rd.dosis,
                        m.id AS medicamento_id,
                        m.nombre AS medicamento_nombre
                 FROM receta_detalles rd
                 JOIN medicamentos m ON rd.medicamento_id = m.id
                 WHERE rd.receta_id = ?";
        $stmt_detalles = $conn->prepare($sql_detalles);
        if (!$stmt_detalles) die("Error SQL detalles: " . $conn->error);
        $stmt_detalles->bind_param("i", $receta_id);
        $stmt_detalles->execute();
        $result_detalles = $stmt_detalles->get_result();
        
        $lista_detalles = [];
        while ($det = $result_detalles->fetch_assoc()) {
            $sql_lote = "SELECT id, codigo_qr, fecha_vencimiento, cantidad 
                         FROM lotes 
                         WHERE medicamento_id = ? AND cantidad > 0
                         ORDER BY fecha_vencimiento ASC
                         LIMIT 1";
            $stmt_lote = $conn->prepare($sql_lote);
            $stmt_lote->bind_param("i", $det['medicamento_id']);
            $stmt_lote->execute();
            $lote = $stmt_lote->get_result()->fetch_assoc();
            
            $det['lote_id'] = $lote['id'] ?? null;
            $det['codigo_qr'] = $lote['codigo_qr'] ?? null;
            $det['fecha_vencimiento'] = $lote['fecha_vencimiento'] ?? null;
            $det['stock_lote'] = $lote['cantidad'] ?? null;
            
            $lista_detalles[] = $det;
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['entregar'])) {
    $receta_id = intval($_POST['receta_id']);
    $admin_id = $_SESSION['usuario_id'];
    $observaciones = trim($_POST['observaciones'] ?? '');

    $conn->begin_transaction();

    try {
        $sql = "INSERT INTO entregas (receta_id, admin_id, observaciones, fecha_entrega) 
                VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Error al preparar inserción: " . $conn->error);
        $stmt->bind_param("iis", $receta_id, $admin_id, $observaciones);
        if (!$stmt->execute()) throw new Exception("Error al ejecutar: " . $stmt->error);
        $stmt->close();

        $sql_detalles_post = "SELECT rd.cantidad, rd.medicamento_id FROM receta_detalles rd WHERE rd.receta_id = ?";
        $stmt_detalles_post = $conn->prepare($sql_detalles_post);
        $stmt_detalles_post->bind_param("i", $receta_id);
        $stmt_detalles_post->execute();
        $detalles_post = $stmt_detalles_post->get_result();

        while ($det_post = $detalles_post->fetch_assoc()) {
            $medicamento_id = $det_post['medicamento_id'];
            $cantidad = $det_post['cantidad'];

            $sql_lote = "SELECT id, cantidad FROM lotes WHERE medicamento_id = ? AND cantidad >= ? ORDER BY fecha_vencimiento ASC LIMIT 1";
            $stmt_lote = $conn->prepare($sql_lote);
            $stmt_lote->bind_param("ii", $medicamento_id, $cantidad);
            $stmt_lote->execute();
            $lote_info = $stmt_lote->get_result()->fetch_assoc();

            if ($lote_info && $lote_info['cantidad'] >= $cantidad) {
                $nuevo_stock = $lote_info['cantidad'] - $cantidad;
                $sql_update = "UPDATE lotes SET cantidad = ? WHERE id = ?";
                $stmt_upd = $conn->prepare($sql_update);
                $stmt_upd->bind_param("ii", $nuevo_stock, $lote_info['id']);
                $stmt_upd->execute();
                $stmt_upd->close();
            } else {
                throw new Exception("Stock insuficiente para el medicamento ID: $medicamento_id");
            }
        }

        $sql = "UPDATE recetas SET estado = 'entregada' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $receta_id);
        if (!$stmt->execute()) throw new Exception("Error al actualizar receta: " . $stmt->error);
        $stmt->close();

        $sql_paciente = "SELECT paciente_id FROM recetas WHERE id = ?";
        $stmt_paciente = $conn->prepare($sql_paciente);
        $stmt_paciente->bind_param("i", $receta_id);
        $stmt_paciente->execute();
        $paciente = $stmt_paciente->get_result()->fetch_assoc();

        if ($paciente) {
            $mensaje_notif = "Su medicamento ha sido entregado. Receta #" . $receta_id;
            $sql_notif = "INSERT INTO notificaciones (paciente_id, mensaje, fecha) 
                          VALUES (?, ?, NOW())";
            $stmt_notif = $conn->prepare($sql_notif);
            $stmt_notif->bind_param("is", $paciente['paciente_id'], $mensaje_notif);
            $stmt_notif->execute();
            $stmt_notif->close();
        }

        $conn->commit();

        $mensaje = "Medicamento entregado exitosamente. Stock descontado correctamente.";
        $tipo_mensaje = "success";

        echo '<script>
            setTimeout(function() {
                window.location.href = "recetas_recibidas.php?estado=entregada";
            }, 2000);
        </script>';

    } catch (Exception $e) {
        $conn->rollback();
        $mensaje = "Error al registrar la entrega: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}
$recetas_aprobadas = [];
if (!isset($_GET['receta'])) {
    $por_pagina = 6;
    $pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
    $offset = ($pagina_actual - 1) * $por_pagina;

    $total_aprobadas_query = $conn->query("SELECT COUNT(*) as total FROM recetas WHERE estado = 'aprobada'");
    $total_aprobadas = $total_aprobadas_query->fetch_assoc()['total'];
    $total_paginas = ceil($total_aprobadas / $por_pagina);

    $sql_aprobadas = "SELECT r.id, 
                              CONCAT(p.nombre, ' ', p.apellido) AS paciente_nombre,
                              u.nombre AS doctor_nombre,
                              r.fecha_receta,
                              p.telefono, p.tiene_whatsapp, p.telefono_tutor
                      FROM recetas r
                      JOIN pacientes p ON r.paciente_id = p.id
                      JOIN usuarios u ON r.doctor_id = u.id
                      WHERE r.estado = 'aprobada'
                      ORDER BY r.fecha_receta ASC
                      LIMIT $por_pagina OFFSET $offset";
    $recetas_aprobadas = $conn->query($sql_aprobadas);
}
?>

<style>
    .main-content { padding-top: 0 !important; }
    .container-fluid.px-4 { margin-top: -15px; }

    :root {
        --en-primary: #7c3aed;
        --en-primary-dark: #4c1d95;
        --en-green: #10b981;
        --en-green-dark: #065f46;
    }
    .en-hero {
        background: linear-gradient(135deg, #4c1d95 0%, #7c3aed 55%, #a78bfa 100%);
        border-radius: 24px;
        padding: 32px 36px;
        color: #fff;
        position: relative;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(124, 58, 237, 0.25);
        margin-bottom: 24px;
    }
    .en-hero::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background-image: radial-gradient(circle, rgba(255,255,255,0.12) 1px, transparent 1px);
        background-size: 20px 20px;
        opacity: 0.6;
    }
    .en-hero::after {
        content: '\f486';
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        position: absolute;
        right: -20px; bottom: -40px;
        font-size: 200px;
        opacity: 0.08;
        transform: rotate(-10deg);
    }
    .en-hero-content { position: relative; z-index: 1; }
    .en-hero-icon {
        width: 68px; height: 68px;
        background: rgba(255,255,255,0.2);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.7rem;
        flex-shrink: 0;
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    }
    .en-hero h2 { font-size: 1.7rem; font-weight: 700; margin: 0 0 4px 0; }
    .en-hero p { margin: 0; opacity: 0.9; font-size: 0.92rem; }
    .en-alert {
        border: none; border-radius: 14px;
        padding: 14px 18px;
        display: flex; align-items: center; gap: 10px;
        font-weight: 500;
        box-shadow: 0 4px 14px rgba(0,0,0,0.06);
        margin-bottom: 20px;
    }
    .en-alert-success { background: linear-gradient(135deg,#ecfdf5,#d1fae5); color: #065f46; }
    .en-alert-danger  { background: linear-gradient(135deg,#fef2f2,#fee2e2); color: #991b1b; }
    .en-info-card {
        background: #fff;
        border-radius: 18px;
        padding: 22px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 4px 16px rgba(15,23,42,0.04);
        height: 100%;
        transition: all 0.3s;
    }
    .en-info-card:hover {
        box-shadow: 0 10px 28px rgba(15,23,42,0.08);
    }
    .en-info-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid #f1f5f9;
    }
    .en-info-header-icon {
        width: 40px; height: 40px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem;
        color: #fff;
        flex-shrink: 0;
    }
    .en-info-header h6 {
        margin: 0;
        font-weight: 700;
        color: #0f172a;
        font-size: 0.9rem;
    }
    .en-info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #f8fafc;
        font-size: 0.85rem;
    }
    .en-info-row:last-child { border-bottom: none; }
    .en-info-label {
        color: #64748b;
        font-weight: 600;
        font-size: 0.78rem;
    }
    .en-info-value {
        color: #0f172a;
        font-weight: 600;
        text-align: right;
    }
    .en-meds-card {
        background: #fff;
        border-radius: 20px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 6px 22px rgba(15,23,42,0.05);
        overflow: hidden;
        margin-bottom: 20px;
    }
    .en-meds-header {
        padding: 18px 24px;
        background: linear-gradient(135deg, #f3e8ff, #ede9fe);
        border-bottom: 1px solid #ddd6fe;
        display: flex; align-items: center; justify-content: space-between;
    }
    .en-meds-header h6 {
        margin: 0; font-weight: 700; color: var(--en-primary-dark);
        font-size: 0.9rem; display: flex; align-items: center; gap: 10px;
    }
    .en-meds-header h6 i {
        width: 30px; height: 30px;
        background: #fff;
        color: var(--en-primary);
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.85rem;
        box-shadow: 0 4px 12px rgba(124,58,237,0.15);
    }
    .en-meds-count {
        background: var(--en-primary);
        color: #fff;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 700;
    }

    .en-table {
        margin: 0;
        font-size: 0.87rem;
    }
    .en-table thead th {
        background: #f8fafc;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        font-weight: 700;
        color: #64748b;
        padding: 14px 18px;
        border-bottom: 1px solid #e2e8f0;
    }
    .en-table tbody td {
        padding: 14px 18px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }
    .en-table tbody tr:hover {
        background: linear-gradient(90deg, rgba(124,58,237,0.03), transparent);
    }

    .en-badge {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 5px 12px; border-radius: 20px;
        font-size: 0.72rem; font-weight: 700;
    }
    .en-badge-purple { background: #ede9fe; color: #6d28d9; }
    .en-badge-green  { background: #dcfce7; color: #166534; }
    .en-badge-amber  { background: #fef3c7; color: #92400e; }
    .en-badge-blue   { background: #dbeafe; color: #1e40af; }
    .en-form-card {
        background: #fff;
        border-radius: 20px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 6px 22px rgba(15,23,42,0.05);
        overflow: hidden;
        margin-bottom: 20px;
    }
    .en-form-header {
        padding: 18px 24px;
        background: linear-gradient(135deg, #ecfdf5, #d1fae5);
        border-bottom: 1px solid #a7f3d0;
    }
    .en-form-header h6 {
        margin: 0; font-weight: 700; color: var(--en-green-dark);
        font-size: 0.9rem; display: flex; align-items: center; gap: 10px;
    }
    .en-form-header h6 i {
        width: 30px; height: 30px;
        background: #fff;
        color: var(--en-green);
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.85rem;
        box-shadow: 0 4px 12px rgba(16,185,129,0.15);
    }
    .en-form-body { padding: 24px; }
    .en-form-body .form-label {
        font-size: 0.8rem; font-weight: 600; color: #475569;
        margin-bottom: 6px;
    }
    .en-form-body .form-control {
        border: 1px solid #e2e8f0;
        padding: 10px 14px;
        font-size: 0.9rem;
        background: #fafbfc;
        transition: all 0.2s;
    }
    .en-form-body .form-control:focus {
        border-color: var(--en-green);
        box-shadow: 0 0 0 4px rgba(16,185,129,0.12);
        background: #fff;
    }

    .en-btn-whatsapp {
        background: linear-gradient(135deg, #047857, #10b981);
        color: #fff; border: none;
        padding: 12px 20px;
        border-radius: 50px;
        font-weight: 700; font-size: 0.88rem;
        transition: all 0.3s;
        display: inline-flex; align-items: center; justify-content: center;
        gap: 8px; width: 100%;
        box-shadow: 0 6px 16px rgba(16,185,129,0.3);
    }
    .en-btn-whatsapp:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 24px rgba(16,185,129,0.45);
        color: #fff;
    }

    .en-btn-deliver {
        background: linear-gradient(135deg, #065f46, #10b981);
        color: #fff; border: none;
        padding: 16px 30px;
        border-radius: 50px;
        font-weight: 700; font-size: 1rem;
        transition: all 0.3s;
        display: inline-flex; align-items: center; justify-content: center;
        gap: 10px; width: 100%;
        box-shadow: 0 10px 26px rgba(16,185,129,0.35);
    }
    .en-btn-deliver:hover {
        transform: translateY(-3px);
        box-shadow: 0 14px 34px rgba(16,185,129,0.5);
        color: #fff;
    }
    .en-check-box {
        background: linear-gradient(135deg, #ecfdf5, #f0fdf4);
        border: 2px solid #a7f3d0;
        border-radius: 14px;
        padding: 16px 20px;
        margin-bottom: 20px;
        transition: all 0.2s;
    }
    .en-check-box:has(input:checked) {
        border-color: var(--en-green);
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    }
    .en-check-box .form-check-input {
        width: 20px; height: 20px;
        margin-top: 2px;
        border: 2px solid var(--en-green);
        cursor: pointer;
    }
    .en-check-box .form-check-input:checked {
        background-color: var(--en-green);
        border-color: var(--en-green);
    }
    .en-check-box .form-check-label {
        font-weight: 600;
        color: #065f46;
        cursor: pointer;
        padding-left: 8px;
        font-size: 0.9rem;
    }
    .en-list-card {
        background: #fff;
        border-radius: 20px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 6px 22px rgba(15,23,42,0.05);
        overflow: hidden;
    }
    .en-list-header {
        padding: 20px 24px;
        background: linear-gradient(180deg, #fafbfc, #f8fafc);
        border-bottom: 1px solid #f1f5f9;
        display: flex; justify-content: space-between; align-items: center;
    }
    .en-list-header h6 { margin: 0; font-weight: 700; color: #0f172a; font-size: 0.95rem; }

    .en-avatar {
        width: 38px; height: 38px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 700; font-size: 0.82rem;
        flex-shrink: 0;
        box-shadow: 0 4px 10px rgba(0,0,0,0.12);
    }

    .en-btn-select {
        background: linear-gradient(135deg, #6d28d9, #8b5cf6);
        color: #fff; border: none;
        padding: 8px 20px;
        border-radius: 50px;
        font-weight: 700;
        font-size: 0.78rem;
        transition: all 0.25s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .en-btn-select:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(124,58,237,0.4);
        color: #fff;
    }

    .pagination { gap: 6px; }
    .page-item .page-link {
        border-radius: 50%;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #e9ecef;
        color: #4c1d95;
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

    @media (max-width: 768px) {
        .en-hero { padding: 22px; }
        .en-hero h2 { font-size: 1.3rem; }
    }
</style>

<div class="container-fluid px-4 fade-in">
    <?php if ($mensaje): ?>
        <div class="en-alert en-alert-<?php echo $tipo_mensaje === 'success' ? 'success' : 'danger'; ?>">
            <i class="fas fa-<?php echo $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            <div><?php echo $mensaje; ?></div>
        </div>
    <?php endif; ?>
    <?php if ($receta): ?>
        <div class="en-hero">
            <div class="en-hero-content d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center">
                    <div class="en-hero-icon me-3">
                        <i class="fas fa-hand-holding-medical"></i>
                    </div>
                    <div>
                        <h2>Entrega de Medicamentos</h2>
                        <p><i class="fas fa-prescription me-2"></i>Receta #<?php echo $receta['id']; ?> — Confirma la entrega al paciente</p>
                    </div>
                </div>
                <a href="recetas_recibidas.php?estado=aprobada" class="btn btn-light rounded-pill px-4 fw-semibold">
                    <i class="fas fa-arrow-left me-1"></i> Volver
                </a>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-10 mx-auto">
                <div class="row g-3 mb-4">
                    <div class="col-md-6 stagger-1">
                        <div class="en-info-card">
                            <div class="en-info-header">
                                <div class="en-info-header-icon" style="background: linear-gradient(135deg, #1e40af, #3b82f6);">
                                    <i class="fas fa-user"></i>
                                </div>
                                <h6>Información del Paciente</h6>
                            </div>
                            <div class="en-info-row">
                                <span class="en-info-label">NOMBRE</span>
                                <span class="en-info-value"><?php echo htmlspecialchars($receta['paciente_nombre']); ?></span>
                            </div>
                            <div class="en-info-row">
                                <span class="en-info-label">TELÉFONO</span>
                                <span class="en-info-value">
                                    <?php 
                                        if (($receta['tiene_whatsapp'] ?? 0) == 1 && !empty($receta['telefono'])) {
                                            echo htmlspecialchars($receta['telefono']);
                                        } elseif (!empty($receta['telefono_tutor'])) {
                                            echo htmlspecialchars($receta['telefono_tutor']) . ' (Tutor)';
                                        } else {
                                            echo 'No registrado';
                                        }
                                    ?>
                                </span>
                            </div>
                            <div class="en-info-row">
                                <span class="en-info-label">EDAD</span>
                                <span class="en-info-value"><?php echo $receta['edad']; ?> años</span>
                            </div>
                            <div class="en-info-row">
                                <span class="en-info-label">SEXO</span>
                                <span class="en-info-value"><?php echo $receta['sexo'] == 'M' ? 'Masculino' : ($receta['sexo'] == 'F' ? 'Femenino' : 'No especificado'); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 stagger-2">
                        <div class="en-info-card">
                            <div class="en-info-header">
                                <div class="en-info-header-icon" style="background: linear-gradient(135deg, #065f46, #10b981);">
                                    <i class="fas fa-user-md"></i>
                                </div>
                                <h6>Información del Doctor</h6>
                            </div>
                            <div class="en-info-row">
                                <span class="en-info-label">NOMBRE</span>
                                <span class="en-info-value"><?php echo htmlspecialchars($receta['doctor_nombre'] ?: 'No especificado'); ?></span>
                            </div>
                            <div class="en-info-row">
                                <span class="en-info-label">ESPECIALIDAD</span>
                                <span class="en-info-value"><?php echo htmlspecialchars($receta['doctor_especialidad'] ?: 'No registrada'); ?></span>
                            </div>
                            <div class="en-info-row">
                                <span class="en-info-label">FECHA RECETA</span>
                                <span class="en-info-value"><?php echo date('d/m/Y', strtotime($receta['fecha_receta'])); ?></span>
                            </div>
                            <div class="en-info-row">
                                <span class="en-info-label">ESTADO</span>
                                <span class="en-badge en-badge-green">
                                    <i class="fas fa-check-circle"></i> Aprobada
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="en-meds-card stagger-3">
                    <div class="en-meds-header">
                        <h6><i class="fas fa-pills"></i> Medicamentos a Entregar</h6>
                        <span class="en-meds-count"><?php echo count($lista_detalles); ?> items</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table en-table">
                            <thead>
                                <tr>
                                    <th>Medicamento</th>
                                    <th>Dosis</th>
                                    <th class="text-center">Cantidad</th>
                                    <th>Lote</th>
                                    <th>Código QR</th>
                                    <th>Vencimiento</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($lista_detalles)): ?>
                                <?php foreach ($lista_detalles as $det): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="en-avatar" style="background: #7c3aed;">
                                                    <i class="fas fa-capsules"></i>
                                                </div>
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($det['medicamento_nombre']); ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($det['dosis'])): ?>
                                                <span class="en-badge en-badge-amber">
                                                    <i class="fas fa-clock"></i>
                                                    <?php echo htmlspecialchars($det['dosis']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted small">No especificada</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="en-badge en-badge-blue">
                                                <i class="fas fa-cubes"></i>
                                                <?php echo (int)$det['cantidad']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($det['lote_id'])): ?>
                                                <span class="en-badge en-badge-purple">
                                                    <i class="fas fa-box"></i>
                                                    Lote #<?php echo $det['lote_id']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="en-badge en-badge-amber">
                                                    <i class="fas fa-exclamation-triangle"></i> Sin lote
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($det['codigo_qr'])): ?>
                                                <code style="font-size: 0.72rem; color: #7c3aed; font-weight: 600;">
                                                    <?php echo htmlspecialchars(substr($det['codigo_qr'], 0, 15)) . '...'; ?>
                                                </code>
                                                <button class="btn btn-sm btn-link p-0 ms-1" onclick="copiarQR('<?php echo $det['codigo_qr']; ?>')">
                                                    <i class="fas fa-copy" style="color: #7c3aed;"></i>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($det['fecha_vencimiento'])): ?>
                                                <span class="text-muted small">
                                                    <?php echo date('d/m/Y', strtotime($det['fecha_vencimiento'])); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted small">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block opacity-50"></i>
                                        No hay medicamentos asociados a esta receta.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <form method="POST" action="" onsubmit="return confirmarEntrega(event)">
                    <input type="hidden" name="receta_id" value="<?php echo $receta['id']; ?>">
                    <input type="hidden" name="entregar" value="1">

                    <div class="en-form-card stagger-4">
                        <div class="en-form-header">
                            <h6><i class="fas fa-clipboard-check"></i> Confirmar Entrega</h6>
                        </div>
                        <div class="en-form-body">

                            <div class="row g-3 mb-3">
                                <div class="col-md-8">
                                    <label class="form-label">Observaciones (opcional)</label>
                                    <textarea name="observaciones" class="form-control rounded-4" rows="3"
                                              placeholder="Ej: Entregado a familiar, paciente no pudo asistir..."></textarea>
                                </div>
                                <div class="col-md-4 d-flex align-items-start">
                                    <?php 
                                        $numero_final = "";
                                        $nombre_contacto = "";
                                        $es_tutor = false;

                                        if (($receta['tiene_whatsapp'] ?? 0) == 1 && !empty($receta['telefono'])) {
                                            $numero_final = $receta['telefono'];
                                            $nombre_contacto = $receta['paciente_nombre'];
                                        } elseif (!empty($receta['telefono_tutor'])) {
                                            $numero_final = $receta['telefono_tutor'];
                                            $nombre_contacto = $receta['nombre_tutor'];
                                            $es_tutor = true;
                                        }

                                        if (!empty($numero_final)): 
                                            $telefono = preg_replace('/[^0-9]/', '', $numero_final);
                                            if (strlen($telefono) < 8) {
                                                $telefono = '505' . $telefono;
                                            }
                                            
                                            $mensaje_whatsapp  = "Hola, *" . $nombre_contacto . "*.\n\n";
                                            
                                            if ($es_tutor) {
                                                $mensaje_whatsapp .= "Le informamos que la receta *N° " . $receta['id'] . "* del paciente *" . $receta['paciente_nombre'] . "* ya está lista para ser retirada en la farmacia.\n\n";
                                            } else {
                                                $mensaje_whatsapp .= "Le informamos que su receta *N° " . $receta['id'] . "* ya está lista para ser retirada en la farmacia.\n\n";
                                            }

                                            $mensaje_whatsapp .= "*Fecha:* " . date('d/m/Y', strtotime($receta['fecha_receta'])) . "\n";
                                            $mensaje_whatsapp .= "*Doctor:* " . ($receta['doctor_nombre'] ?? 'No registrado') . "\n\n";
                                            $mensaje_whatsapp .= "*Medicamentos:*\n";

                                            if (!empty($lista_detalles)) {
                                                foreach ($lista_detalles as $det) {
                                                    $cantidad = (int)$det['cantidad'];
                                                    $unidad = ($cantidad === 1) ? 'unidad' : 'unidades';
                                                    
                                                    $mensaje_whatsapp .= "• *" . $det['medicamento_nombre'] . "*\n";
                                                    $mensaje_whatsapp .= "   Dosis: " . (!empty($det['dosis']) ? $det['dosis'] : 'No especificada') . "\n";
                                                    $mensaje_whatsapp .= "   Cantidad: " . $cantidad . " " . $unidad . "\n";
                                                }
                                            } else {
                                                $mensaje_whatsapp .= "• Verificar con el farmacéutico.\n";
                                            }

                                            if (!empty($receta['observaciones'])) {
                                                $mensaje_whatsapp .= "\n*Observaciones:*\n" . $receta['observaciones'] . "\n";
                                            }

                                            $mensaje_whatsapp .= "\nPor favor, pase a retirarlos. Gracias.\n\n";
                                            $mensaje_whatsapp .= "Atentamente,\n";
                                            $mensaje_whatsapp .= "*Servicio de Farmacia*\n";
                                            $mensaje_whatsapp .= "Centro de Salud";
                                            $mensaje_whatsapp = urlencode($mensaje_whatsapp);
                                            $whatsapp_url = "https://wa.me/" . $telefono . "?text=" . $mensaje_whatsapp;
                                        ?>
                                            <a href="<?php echo $whatsapp_url; ?>" target="_blank" class="en-btn-whatsapp">
                                                <i class="fab fa-whatsapp" style="font-size: 1.2rem;"></i> Notificar por WhatsApp
                                            </a>
                                        <?php else: ?>
                                            <button class="en-btn-whatsapp" disabled style="opacity: 0.5;">
                                                <i class="fab fa-whatsapp"></i> Sin teléfono registrado
                                            </button>
                                        <?php endif; ?>
                                </div>
                            </div>

                            <div class="en-check-box">
                                <div class="form-check d-flex align-items-start">
                                    <input class="form-check-input" type="checkbox" id="confirmar" required>
                                    <label class="form-check-label" for="confirmar">
                                        Confirmo que los medicamentos han sido entregados correctamente al paciente
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="en-btn-deliver">
                                <i class="fas fa-check-circle" style="font-size: 1.2rem;"></i>
                                Confirmar Entrega al Paciente
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    <?php else: ?>
        <div class="en-hero">
            <div class="en-hero-content d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center">
                    <div class="en-hero-icon me-3">
                        <i class="fas fa-hand-holding-medical"></i>
                    </div>
                    <div>
                        <h2>Entrega de Medicamentos</h2>
                        <p><i class="fas fa-list-check me-2"></i>Selecciona una receta aprobada para entregar</p>
                    </div>
                </div>
                <a href="recetas_recibidas.php" class="btn btn-light rounded-pill px-4 fw-semibold">
                    <i class="fas fa-arrow-left me-1"></i> Ir a Recetas
                </a>
            </div>
        </div>
        <div class="en-list-card">
            <div class="en-list-header">
                <h6><i class="fas fa-list me-2" style="color: #7c3aed;"></i>Recetas Aprobadas Pendientes</h6>
                <span class="en-badge en-badge-purple">
                    <?php echo $total_aprobadas; ?> recetas
                </span>
            </div>
            <div class="table-responsive">
                <table class="table en-table">
                    <thead>
                        <tr>
                            <th style="width: 8%;">ID</th>
                            <th style="width: 35%;">Paciente</th>
                            <th style="width: 25%;">Doctor</th>
                            <th style="width: 17%;">Fecha</th>
                            <th style="width: 15%;" class="text-end">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($recetas_aprobadas && $recetas_aprobadas->num_rows > 0): ?>
                        <?php while ($row = $recetas_aprobadas->fetch_assoc()): 
                            $inicial = strtoupper(substr($row['paciente_nombre'], 0, 2));
                        ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-secondary rounded-pill px-2 py-1">
                                        #<?php echo $row['id']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="en-avatar" style="background: <?php echo '#' . substr(md5($row['paciente_nombre']), 0, 6); ?>;">
                                            <?php echo $inicial; ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['paciente_nombre']); ?></div>
                                            <?php 
                                                $tel_mostrar = (($row['tiene_whatsapp'] ?? 0) == 1 && !empty($row['telefono'])) ? $row['telefono'] : $row['telefono_tutor'];
                                                if (!empty($tel_mostrar)): 
                                            ?>
                                                <small class="text-muted"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($tel_mostrar); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="en-avatar" style="background: #6d28d9; font-size: 0.75rem;">
                                            <i class="fas fa-user-md"></i>
                                        </div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($row['doctor_nombre']); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-muted small">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('d/m/Y', strtotime($row['fecha_receta'])); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="?receta=<?php echo $row['id']; ?>" class="en-btn-select">
                                        <i class="fas fa-hand-holding-medical"></i> Entregar
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fas fa-check-circle fa-4x mb-3 d-block" style="color: #10b981; opacity: 0.5;"></i>
                                <h6 class="fw-bold">No hay recetas pendientes de entrega</h6>
                                <p class="small mb-0">Todas las recetas aprobadas ya han sido entregadas</p>
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
                            <a class="page-link" href="?pagina=<?php echo $pagina_actual - 1; ?>"><i class="fas fa-chevron-left"></i></a>
                        </li>
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <li class="page-item <?php echo $pagina_actual == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $pagina_actual >= $total_paginas ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?pagina=<?php echo $pagina_actual + 1; ?>"><i class="fas fa-chevron-right"></i></a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function copiarQR(codigo) {
    navigator.clipboard.writeText(codigo).then(() => {
        Swal.fire({
            title: '¡Copiado!',
            text: 'Código QR copiado al portapapeles',
            icon: 'success',
            timer: 1500,
            showConfirmButton: false
        });
    });
}

function confirmarEntrega(event) {
    event.preventDefault();
    const form = event.target;

    Swal.fire({
        title: '¿Confirmar entrega?',
        html: `
            <div style="text-align: center;">
                <i class="fas fa-hand-holding-medical" style="font-size: 3rem; color: #10b981; margin-bottom: 15px;"></i>
                <p style="font-size: 1rem; margin-bottom: 10px;">¿Estás seguro de que los medicamentos han sido entregados correctamente al paciente?</p>
                <p style="font-size: 0.85rem; color: #991b1b; background: #fef2f2; padding: 10px; border-radius: 10px; margin: 0;">
                    <strong>Importante:</strong> El stock será descontado automáticamente del inventario.
                </p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-check"></i> Sí, confirmar entrega',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#94a3b8',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });

    return false;
}
</script>

<?php require_once '../../includes/footer.php'; ?>