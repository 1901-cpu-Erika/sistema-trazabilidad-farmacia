<?php
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('America/Managua');
require_once '../../config/database.php';

$titulo_pagina = "Gestión de Doctores";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

if ($_SESSION['tipo_usuario'] != 'admin') {
    header("Location: ../../login.php");
    exit();
}

$mensaje = '';
$tipo_mensaje = '';
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    $sql = "SELECT COUNT(*) as total FROM recetas WHERE doctor_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $recetas = $stmt->get_result()->fetch_assoc()['total'];

    if ($recetas > 0) {
        $mensaje = "No se puede eliminar el doctor porque tiene <strong>$recetas</strong> recetas asociadas.";
        $tipo_mensaje = "danger";
    } else {
        $sql = "DELETE FROM usuarios WHERE id = ? AND tipo = 'doctor'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $mensaje = "Doctor eliminado correctamente.";
            $tipo_mensaje = "success";
        } else {
            $mensaje = "Error al eliminar el doctor.";
            $tipo_mensaje = "danger";
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_doctor'])) {
    $id = $_POST['id'] ?? '';
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $password = $_POST['password'] ?? '';
    $estado = $_POST['estado'];
    $subtipo = $_POST['subtipo'] ?? 'fijo';
    $especialidad_id = !empty($_POST['especialidad_id']) ? intval($_POST['especialidad_id']) : null;

    if (empty($nombre) || empty($email)) {
        $mensaje = "Nombre y email son obligatorios.";
        $tipo_mensaje = "danger";
    } else {
        if (empty($id)) {
            $password_final = !empty($password) ? $password : '123456';
            
            $sql = "INSERT INTO usuarios (nombre, email, password, tipo, subtipo, estado, especialidad_id) 
                    VALUES (?, ?, ?, 'doctor', ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssi", $nombre, $email, $password_final, $subtipo, $estado, $especialidad_id);

            if ($stmt->execute()) {
                $etiqueta = $subtipo === 'turno' ? 'Doctor de Turno' : 'Doctor';
                $mensaje = "$etiqueta registrado correctamente. Contraseña: <strong>" . ($password ?: '123456') . "</strong>";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al registrar: el email puede estar duplicado.";
                $tipo_mensaje = "danger";
            }
        } else {
            if (!empty($password)) {
                $sql = "UPDATE usuarios SET nombre=?, email=?, password=?, estado=?, subtipo=?, especialidad_id=? WHERE id=? AND tipo='doctor'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssii", $nombre, $email, $password, $estado, $subtipo, $especialidad_id, $id);
            } else {
                $sql = "UPDATE usuarios SET nombre=?, email=?, estado=?, subtipo=?, especialidad_id=? WHERE id=? AND tipo='doctor'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssii", $nombre, $email, $estado, $subtipo, $especialidad_id, $id);
            }
            if ($stmt->execute()) {
                $mensaje = "Doctor actualizado correctamente.";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al actualizar el doctor.";
                $tipo_mensaje = "danger";
            }
        }
    }
}
$doctores = $conn->query("
    SELECT u.*, e.nombre AS especialidad_nombre 
    FROM usuarios u
    LEFT JOIN especialidades e ON u.especialidad_id = e.id
    WHERE u.tipo = 'doctor' 
    ORDER BY FIELD(u.subtipo,'fijo','turno'), u.nombre ASC
");

$total_doctores = $doctores->num_rows;
$activos = 0;
$inactivos = 0;
$fijos = 0;
$turnos = 0;
$doctores->data_seek(0);
while ($d = $doctores->fetch_assoc()) {
    if ($d['estado'] == 'activo') $activos++;
    else $inactivos++;
    if (($d['subtipo'] ?? 'fijo') == 'fijo') $fijos++;
    else $turnos++;
}
$doctores->data_seek(0);

$especialidades = $conn->query("SELECT MIN(id) as id, nombre FROM especialidades GROUP BY nombre ORDER BY nombre");
if (!$especialidades) {
    $conn->query("CREATE TABLE IF NOT EXISTS especialidades (id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(100) NOT NULL, estado VARCHAR(20) DEFAULT 'activo')");
    $conn->query("INSERT IGNORE INTO especialidades (nombre) VALUES ('Medicina General'),('Medicina Interna'),('Cardiología'),('Pediatría'),('Ginecología'),('Oftalmología'),('Dermatología'),('Ortopedia'),('Neurología'),('Psiquiatría')");
    $especialidades = $conn->query("SELECT MIN(id) as id, nombre FROM especialidades GROUP BY nombre ORDER BY nombre");
}

$doctor_editar = null;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $id = intval($_GET['editar']);
    $sql = "SELECT * FROM usuarios WHERE id = ? AND tipo = 'doctor'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $doctor_editar = $stmt->get_result()->fetch_assoc();
}
?>

<style>
    :root {
        --gd-primary: #dc2626;
        --gd-primary-dark: #7f1d1d;
        --gd-primary-light: #fef2f2;
        --gd-blue: #2563eb;
        --gd-green: #059669;
        --gd-purple: #7c3aed;
        --gd-amber: #d97706;
    }
    .gd-hero {
        background: linear-gradient(135deg, #7f1d1d 0%, #dc2626 55%, #ef4444 100%);
        border-radius: 24px;
        padding: 32px 36px;
        color: #fff;
        position: relative;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(220, 38, 38, 0.25);
        margin-bottom: 24px;
    }
    .gd-hero::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background-image: radial-gradient(circle, rgba(255,255,255,0.12) 1px, transparent 1px);
        background-size: 20px 20px;
        opacity: 0.6;
    }
    .gd-hero::after {
        content: '\f0f1';
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        position: absolute;
        right: -20px;
        bottom: -40px;
        font-size: 200px;
        opacity: 0.08;
        transform: rotate(-10deg);
    }
    .gd-hero-content { position: relative; z-index: 1; }
    .gd-hero-icon {
        width: 68px;
        height: 68px;
        background: rgba(255,255,255,0.2);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.7rem;
        flex-shrink: 0;
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    }
    .gd-hero h2 {
        font-size: 1.7rem;
        font-weight: 700;
        margin: 0 0 4px 0;
    }
    .gd-hero p {
        margin: 0;
        opacity: 0.9;
        font-size: 0.92rem;
    }
    .gd-kpi {
        background: #fff;
        border-radius: 18px;
        padding: 20px 22px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05);
        transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        height: 100%;
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .gd-kpi::before {
        content: '';
        position: absolute;
        top: 0; left: 0;
        width: 4px; height: 100%;
        background: var(--kpi-color, #dc2626);
        transition: width 0.35s;
    }
    .gd-kpi:hover {
        transform: translateY(-5px);
        box-shadow: 0 14px 32px rgba(15, 23, 42, 0.1);
    }
    .gd-kpi:hover::before { width: 8px; }
    .gd-kpi-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        color: #fff;
        flex-shrink: 0;
        box-shadow: 0 6px 16px rgba(0,0,0,0.15);
        transition: transform 0.35s;
    }
    .gd-kpi:hover .gd-kpi-icon {
        transform: scale(1.1) rotate(-5deg);
    }
    .gd-kpi-label {
        font-size: 0.7rem;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 3px;
        display: block;
    }
    .gd-kpi-value {
        font-size: 1.7rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1;
        margin: 0;
    }
    .gd-form-card {
        background: #fff;
        border-radius: 20px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 6px 22px rgba(15, 23, 42, 0.05);
        overflow: hidden;
        position: sticky;
        top: 20px;
    }
    .gd-form-header {
        padding: 20px 24px;
        background: linear-gradient(135deg, #fef2f2, #fee2e2);
        border-bottom: 1px solid #fecaca;
    }
    .gd-form-header h6 {
        margin: 0;
        font-weight: 700;
        color: var(--gd-primary-dark);
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .gd-form-header h6 i {
        width: 32px; height: 32px;
        background: #fff;
        color: var(--gd-primary);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        box-shadow: 0 4px 12px rgba(220,38,38,0.15);
    }
    .gd-form-body { padding: 24px; }
    .gd-form-body .form-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #475569;
        margin-bottom: 6px;
        display: block;
    }
    .gd-form-body .input-group-text {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: var(--gd-primary);
        border-right: none;
    }
    .gd-form-body .form-control,
    .gd-form-body .form-select {
        border: 1px solid #e2e8f0;
        padding: 10px 14px;
        font-size: 0.9rem;
        background: #fafbfc;
        transition: all 0.2s;
    }
    .gd-form-body .form-control:focus,
    .gd-form-body .form-select:focus {
        border-color: var(--gd-primary);
        box-shadow: 0 0 0 4px rgba(220,38,38,0.12);
        background: #fff;
    }

    .gd-btn-primary {
        background: linear-gradient(135deg, #7f1d1d, #dc2626);
        color: #fff;
        border: none;
        padding: 13px 24px;
        border-radius: 50px;
        font-weight: 700;
        font-size: 0.9rem;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        box-shadow: 0 8px 20px rgba(220,38,38,0.28);
    }
    .gd-btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 28px rgba(220,38,38,0.4);
        color: #fff;
    }
    .gd-table-card {
        background: #fff;
        border-radius: 20px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 6px 22px rgba(15, 23, 42, 0.05);
        overflow: hidden;
    }
    .gd-table-header {
        padding: 20px 24px;
        background: linear-gradient(180deg, #fafbfc, #f8fafc);
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }
    .gd-table-header h6 {
        margin: 0;
        font-weight: 700;
        color: #0f172a;
        font-size: 0.95rem;
    }
    .gd-count {
        background: var(--gd-primary);
        color: #fff;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
    }
    .gd-search {
        position: relative;
        max-width: 280px;
    }
    .gd-search input {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        padding: 9px 14px 9px 38px;
        font-size: 0.85rem;
        width: 100%;
        transition: all 0.2s;
        background: #f8fafc;
    }
    .gd-search input:focus {
        border-color: var(--gd-primary);
        box-shadow: 0 0 0 4px rgba(220,38,38,0.12);
        background: #fff;
        outline: none;
    }
    .gd-search::before {
        content: '\f002';
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        position: absolute;
        left: 14px; top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 0.85rem;
    }

    .gd-table {
        margin: 0;
        font-size: 0.87rem;
    }
    .gd-table thead th {
        background: #f8fafc;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        font-weight: 700;
        color: #64748b;
        padding: 14px 18px;
        border-bottom: 1px solid #e2e8f0;
    }
    .gd-table tbody td {
        padding: 14px 18px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }
    .gd-table tbody tr {
        transition: all 0.18s;
    }
    .gd-table tbody tr:hover {
        background: linear-gradient(90deg, rgba(220,38,38,0.03), transparent);
    }

    .gd-avatar {
        width: 38px; height: 38px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        color: #fff;
        font-weight: 700;
        font-size: 0.85rem;
        flex-shrink: 0;
        box-shadow: 0 4px 10px rgba(0,0,0,0.12);
    }

    .gd-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.73rem;
        font-weight: 700;
    }
    .gd-badge-fijo   { background: #dbeafe; color: #1e40af; }
    .gd-badge-turno  { background: #fef3c7; color: #92400e; }
    .gd-badge-activo { background: #dcfce7; color: #166534; }
    .gd-badge-inactivo { background: #e2e8f0; color: #475569; }
    .gd-badge-reca { background: #ede9fe; color: #6d28d9; }

    .gd-action-btn {
        width: 34px; height: 34px;
        border-radius: 10px;
        border: none;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    .gd-btn-edit   { background: #fef3c7; color: #b45309; }
    .gd-btn-edit:hover   { background: #fcd34d; transform: translateY(-2px); }
    .gd-btn-delete { background: #fee2e2; color: #b91c1c; }
    .gd-btn-delete:hover { background: #fecaca; transform: translateY(-2px); }
    .gd-btn-view   { background: #dbeafe; color: #1d4ed8; }
    .gd-btn-view:hover   { background: #bfdbfe; transform: translateY(-2px); }
    .gd-alert {
        border: none;
        border-radius: 14px;
        padding: 14px 18px;
        display: flex; align-items: center; gap: 10px;
        font-weight: 500;
        box-shadow: 0 4px 14px rgba(0,0,0,0.06);
        margin-bottom: 20px;
    }
    .gd-alert-success { background: linear-gradient(135deg,#ecfdf5,#d1fae5); color: #065f46; }
    .gd-alert-danger  { background: linear-gradient(135deg,#fef2f2,#fee2e2); color: #991b1b; }
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

    @media (max-width: 768px) {
        .gd-hero { padding: 22px; }
        .gd-hero h2 { font-size: 1.3rem; }
        .gd-form-card { position: static; }
    }
</style>

<div class="container-fluid px-4 fade-in">
    <div class="gd-hero">
        <div class="gd-hero-content d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center">
                <div class="gd-hero-icon me-3">
                    <i class="fas fa-user-md"></i>
                </div>
                <div>
                    <h2>Gestión de Doctores</h2>
                    <p><i class="fas fa-hospital-user me-2"></i>Administra los médicos registrados en el sistema</p>
                </div>
            </div>
            <a href="dashboard.php" class="btn btn-light rounded-pill px-4 fw-semibold">
                <i class="fas fa-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>
    <?php if ($mensaje): ?>
        <div class="gd-alert gd-alert-<?php echo $tipo_mensaje === 'success' ? 'success' : 'danger'; ?>">
            <i class="fas fa-<?php echo $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            <div><?php echo $mensaje; ?></div>
        </div>
    <?php endif; ?>
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6 stagger-1">
            <div class="gd-kpi" style="--kpi-color: #dc2626;">
                <div class="gd-kpi-icon" style="background: linear-gradient(135deg, #7f1d1d, #dc2626);">
                    <i class="fas fa-user-md"></i>
                </div>
                <div>
                    <span class="gd-kpi-label">Total Doctores</span>
                    <div class="gd-kpi-value"><?php echo $total_doctores; ?></div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 stagger-2">
            <div class="gd-kpi" style="--kpi-color: #2563eb;">
                <div class="gd-kpi-icon" style="background: linear-gradient(135deg, #1e40af, #3b82f6);">
                    <i class="fas fa-user-check"></i>
                </div>
                <div>
                    <span class="gd-kpi-label">Doctores Fijos</span>
                    <div class="gd-kpi-value"><?php echo $fijos; ?></div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 stagger-3">
            <div class="gd-kpi" style="--kpi-color: #d97706;">
                <div class="gd-kpi-icon" style="background: linear-gradient(135deg, #b45309, #f59e0b);">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <span class="gd-kpi-label">De Turno</span>
                    <div class="gd-kpi-value"><?php echo $turnos; ?></div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 stagger-4">
            <div class="gd-kpi" style="--kpi-color: #059669;">
                <div class="gd-kpi-icon" style="background: linear-gradient(135deg, #065f46, #10b981);">
                    <i class="fas fa-circle-check"></i>
                </div>
                <div>
                    <span class="gd-kpi-label">Activos</span>
                    <div class="gd-kpi-value"><?php echo $activos; ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-lg-4 stagger-5">
            <div class="gd-form-card">
                <div class="gd-form-header">
                    <h6>
                        <i class="fas fa-<?php echo $doctor_editar ? 'pen' : 'plus'; ?>"></i>
                        <?php echo $doctor_editar ? 'Editar Doctor' : 'Registrar Nuevo Doctor'; ?>
                    </h6>
                </div>
                <div class="gd-form-body">
                    <form method="POST" action="">
                        <?php if ($doctor_editar): ?>
                            <input type="hidden" name="id" value="<?php echo $doctor_editar['id']; ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Nombre Completo *</label>
                            <input type="text" class="form-control rounded-pill" 
                                   name="nombre" value="<?php echo $doctor_editar ? htmlspecialchars($doctor_editar['nombre']) : ''; ?>" 
                                   placeholder="Ej: Dr. Juan Rodríguez" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control rounded-pill" 
                                   name="email" value="<?php echo $doctor_editar ? htmlspecialchars($doctor_editar['email']) : ''; ?>" 
                                   placeholder="correo@centro.com" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                Contraseña
                                <?php if ($doctor_editar): ?>
                                    <small class="text-muted fw-normal">(Dejar en blanco para no cambiar)</small>
                                <?php endif; ?>
                            </label>
                            <input type="password" class="form-control rounded-pill" 
                                   name="password" placeholder="••••••••"
                                   <?php echo !$doctor_editar ? 'required' : ''; ?>>
                            <?php if (!$doctor_editar): ?>
                                <small class="text-muted">Por defecto: <strong>123456</strong></small>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tipo de Doctor *</label>
                            <select name="subtipo" class="form-select rounded-pill">
                                <option value="fijo" <?php echo ($doctor_editar && ($doctor_editar['subtipo'] ?? 'fijo') == 'fijo') ? 'selected' : ''; ?>>
                                    Doctor Fijo (permanente)
                                </option>
                                <option value="turno" <?php echo ($doctor_editar && ($doctor_editar['subtipo'] ?? '') == 'turno') ? 'selected' : ''; ?>>
                                    Doctor de Turno (temporal)
                                </option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Especialidad</label>
                            <select name="especialidad_id" class="form-select rounded-pill">
                                <option value="">Sin especialidad</option>
                                <?php if ($especialidades): $especialidades->data_seek(0); while ($esp = $especialidades->fetch_assoc()): ?>
                                    <option value="<?php echo $esp['id']; ?>"
                                        <?php echo ($doctor_editar && $doctor_editar['especialidad_id'] == $esp['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($esp['nombre']); ?>
                                    </option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Estado *</label>
                            <select name="estado" class="form-select rounded-pill">
                                <option value="activo" <?php echo ($doctor_editar && $doctor_editar['estado'] == 'activo') ? 'selected' : ''; ?>>Activo</option>
                                <option value="inactivo" <?php echo ($doctor_editar && $doctor_editar['estado'] == 'inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="guardar_doctor" class="gd-btn-primary">
                                <i class="fas fa-save"></i>
                                <?php echo $doctor_editar ? 'Actualizar Doctor' : 'Registrar Doctor'; ?>
                            </button>
                            <?php if ($doctor_editar): ?>
                                <a href="gestion_doctores.php" class="btn btn-outline-secondary rounded-pill">
                                    <i class="fas fa-times me-2"></i>Cancelar
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8 stagger-5">
            <div class="gd-table-card">
                <div class="gd-table-header">
                    <div>
                        <h6><i class="fas fa-list me-2 text-danger"></i>Doctores Registrados</h6>
                    </div>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="gd-search">
                            <input type="text" id="buscar" placeholder="Buscar por nombre o email...">
                        </div>
                        <span class="gd-count"><?php echo $total_doctores; ?> registros</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table gd-table" id="tablaDoctores">
                        <thead>
                            <tr>
                                <th style="width: 70px;">ID</th>
                                <th>Doctor</th>
                                <th>Email</th>
                                <th>Especialidad</th>
                                <th>Recetas</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                                <th class="text-end" style="width: 130px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($doctores->num_rows > 0): ?>
                            <?php while ($doc = $doctores->fetch_assoc()):
                                $sql_recetas = "SELECT COUNT(*) as total FROM recetas WHERE doctor_id = ?";
                                $stmt = $conn->prepare($sql_recetas);
                                $stmt->bind_param("i", $doc['id']);
                                $stmt->execute();
                                $total_recetas = $stmt->get_result()->fetch_assoc()['total'];
                                $es_turno = (($doc['subtipo'] ?? 'fijo') === 'turno');
                            ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-light text-secondary rounded-pill px-2 py-1">
                                            #<?php echo $doc['id']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="gd-avatar" style="background: <?php echo '#' . substr(md5($doc['nombre']), 0, 6); ?>;">
                                                <?php echo strtoupper(substr($doc['nombre'], 0, 2)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($doc['nombre']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><small class="text-muted"><?php echo htmlspecialchars($doc['email']); ?></small></td>
                                    <td>
                                        <?php if (!empty($doc['especialidad_nombre'])): ?>
                                            <span class="gd-badge gd-badge-reca">
                                                <i class="fas fa-stethoscope"></i>
                                                <?php echo htmlspecialchars($doc['especialidad_nombre']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="gd-badge gd-badge-reca">
                                            <i class="fas fa-prescription"></i> <?php echo $total_recetas; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($es_turno): ?>
                                            <span class="gd-badge gd-badge-turno">
                                                <i class="fas fa-clock"></i> Turno
                                            </span>
                                        <?php else: ?>
                                            <span class="gd-badge gd-badge-fijo">
                                                <i class="fas fa-user-check"></i> Fijo
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="gd-badge gd-badge-<?php echo $doc['estado'] == 'activo' ? 'activo' : 'inactivo'; ?>">
                                            <?php echo ucfirst($doc['estado']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <button class="gd-action-btn gd-btn-view me-1" title="Ver recetas" onclick="verRecetas(<?php echo $doc['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="gd-action-btn gd-btn-edit me-1" title="Editar" onclick="editarDoctor(<?php echo $doc['id']; ?>)">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <button class="gd-action-btn gd-btn-delete" title="Eliminar" onclick="eliminarDoctor(<?php echo $doc['id']; ?>, '<?php echo addslashes($doc['nombre']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-user-slash fa-3x mb-3 d-block opacity-50"></i>
                                    <h6 class="fw-bold">No hay doctores registrados</h6>
                                    <p class="small mb-0">Registra el primer doctor usando el formulario</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function editarDoctor(id) {
    window.location.href = 'gestion_doctores.php?editar=' + id;
}

function eliminarDoctor(id, nombre) {
    Swal.fire({
        title: '¿Eliminar doctor?',
        html: `¿Estás seguro de eliminar a <strong>${nombre}</strong>?<br><br>
               <small class="text-danger">Solo se puede eliminar si no tiene recetas asociadas.</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'gestion_doctores.php?eliminar=' + id;
        }
    });
}

function verRecetas(id) {
    window.location.href = 'recetas_por_doctor.php?id=' + id;
}
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('buscar');
    if (input) {
        input.addEventListener('keyup', function() {
            const filter = this.value.toUpperCase();
            const table = document.getElementById('tablaDoctores');
            const rows = table.getElementsByTagName('tr');
            for (let i = 1; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let found = false;
                for (let j = 0; j < cells.length - 1; j++) {
                    const text = cells[j].textContent || cells[j].innerText;
                    if (text.toUpperCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
                rows[i].style.display = found ? '' : 'none';
            }
        });
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>