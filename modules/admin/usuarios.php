<?php
if (session_status() === PHP_SESSION_NONE) {
    session_save_path('/tmp'); // Esto es clave para Vercel
    session_start();
}

date_default_timezone_set('America/Managua');
require_once '../../config/database.php';

if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header("Location: ../../login.php");
    exit();
}

$titulo_pagina = "Gestión de Usuarios";

$mensaje = '';
$tipo_mensaje = '';

$existe_jefe = (int)$conn->query(
    "SELECT COUNT(*) AS t FROM usuarios WHERE tipo='jefe_farmacia'"
)->fetch_assoc()['t'] > 0;

$existe_farmacia = (int)$conn->query(
    "SELECT COUNT(*) AS t FROM usuarios WHERE tipo='farmacia'"
)->fetch_assoc()['t'] > 0;

$existe_admin = (int)$conn->query(
    "SELECT COUNT(*) AS t FROM usuarios WHERE tipo='admin'"
)->fetch_assoc()['t'] > 0;

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['cambiar_estado_usuario'])
) {
    $id_estado = filter_input(
        INPUT_POST,
        'usuario_id_estado',
        FILTER_VALIDATE_INT
    );

    $nuevo_estado = $_POST['nuevo_estado'] ?? '';

    if (
        !$id_estado ||
        !in_array($nuevo_estado, ['activo', 'inactivo'], true)
    ) {
        $mensaje = "Los datos para cambiar el estado no son válidos.";
        $tipo_mensaje = "danger";
    } elseif (
        $id_estado === (int)($_SESSION['usuario_id'] ?? 0) &&
        $nuevo_estado === 'inactivo'
    ) {
        $mensaje = "No puedes desactivar tu propia cuenta mientras tienes la sesión iniciada.";
        $tipo_mensaje = "danger";
    } else {
        $stmt = $conn->prepare(
            "SELECT id, tipo, estado FROM usuarios WHERE id = ?"
        );
        $stmt->bind_param("i", $id_estado);
        $stmt->execute();
        $usuario_estado = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$usuario_estado) {
            $mensaje = "El usuario que intentas modificar no existe.";
            $tipo_mensaje = "danger";
        } elseif (
            $usuario_estado['tipo'] === 'admin' &&
            $nuevo_estado === 'inactivo' &&
            $usuario_estado['estado'] === 'activo'
        ) {
            $resultado_admin = $conn->query(
                "SELECT COUNT(*) AS total
                 FROM usuarios
                 WHERE tipo = 'admin' AND estado = 'activo'"
            );

            $total_admin_activos = (int)$resultado_admin->fetch_assoc()['total'];

            if ($total_admin_activos <= 1) {
                $mensaje = "No puedes desactivar al único administrador activo del sistema.";
                $tipo_mensaje = "danger";
            }
        }

        if ($mensaje === '') {
            $stmt = $conn->prepare(
                "UPDATE usuarios SET estado = ? WHERE id = ?"
            );
            $stmt->bind_param("si", $nuevo_estado, $id_estado);

            if ($stmt->execute()) {
                $mensaje = $nuevo_estado === 'activo'
                    ? "Usuario activado correctamente."
                    : "Usuario desactivado correctamente.";

                $tipo_mensaje = "success";
            } else {
                $mensaje = "No se pudo cambiar el estado del usuario.";
                $tipo_mensaje = "danger";
            }

            $stmt->close();
        }
    }
}

if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $id_eliminar = (int)$_GET['eliminar'];

    $stmt = $conn->prepare("SELECT tipo FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id_eliminar);
    $stmt->execute();
    $usuario_eliminar = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$usuario_eliminar) {
        $mensaje = "El usuario que intentas eliminar no existe.";
        $tipo_mensaje = "danger";
    } elseif ($usuario_eliminar['tipo'] === 'admin') {
        $total_admins = (int)$conn->query(
            "SELECT COUNT(*) AS t FROM usuarios WHERE tipo='admin'"
        )->fetch_assoc()['t'];

        if ($total_admins <= 1) {
            $mensaje = "No puedes eliminar al único administrador del sistema.";
            $tipo_mensaje = "danger";
        }
    }

    if ($mensaje === '') {
        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $id_eliminar);

        if ($stmt->execute()) {
            $mensaje = "Usuario eliminado correctamente.";
            $tipo_mensaje = "success";
        } else {
            $mensaje = "No se pudo eliminar el usuario. Puede tener registros relacionados.";
            $tipo_mensaje = "danger";
        }

        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_usuario'])) {
    $id = isset($_POST['id']) && $_POST['id'] !== ''
        ? (int)$_POST['id']
        : 0;

    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $codigo_minsa = trim($_POST['codigo_minsa'] ?? '');
    $password = $_POST['password'] ?? '';
    $estado = $_POST['estado'] ?? 'activo';
    $tipo = $_POST['tipo'] ?? '';
    $subtipo = $_POST['subtipo'] ?? 'fijo';

    $especialidad_id = !empty($_POST['especialidad_id'])
        ? (int)$_POST['especialidad_id']
        : null;

    $roles_validos = ['admin', 'jefe_farmacia', 'farmacia', 'doctor'];
    $roles_unicos = ['admin', 'jefe_farmacia', 'farmacia'];

    if (!in_array($tipo, $roles_validos, true)) {
        $mensaje = "Tipo de usuario inválido.";
        $tipo_mensaje = "danger";
    } elseif ($nombre === '' || $email === '') {
        $mensaje = "El nombre y el correo electrónico son obligatorios.";
        $tipo_mensaje = "danger";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "Introduce un correo electrónico válido.";
        $tipo_mensaje = "danger";
    } elseif ($tipo === 'doctor' && $codigo_minsa === '') {
        $mensaje = "El Código MINSA es obligatorio para los doctores.";
        $tipo_mensaje = "danger";
    } elseif (!in_array($estado, ['activo', 'inactivo'], true)) {
        $mensaje = "El estado seleccionado no es válido.";
        $tipo_mensaje = "danger";
    } else {
        if ($tipo !== 'doctor') {
            $subtipo = 'fijo';
            $especialidad_id = null;
        }

        if (!in_array($subtipo, ['fijo', 'turno'], true)) {
            $subtipo = 'fijo';
        }

        if (in_array($tipo, $roles_unicos, true)) {
            $sql_unico = "SELECT COUNT(*) AS t FROM usuarios WHERE tipo = ?";

            if ($id > 0) {
                $sql_unico .= " AND id <> ?";
                $stmt = $conn->prepare($sql_unico);
                $stmt->bind_param("si", $tipo, $id);
            } else {
                $stmt = $conn->prepare($sql_unico);
                $stmt->bind_param("s", $tipo);
            }

            $stmt->execute();
            $ya_existe = (int)$stmt->get_result()->fetch_assoc()['t'];
            $stmt->close();

            if ($ya_existe > 0) {
                $mensaje = "Ya existe un usuario con el rol seleccionado. Solo puede haber uno.";
                $tipo_mensaje = "danger";
            }
        }

        if ($mensaje === '') {
            $sql_email = "SELECT COUNT(*) AS t FROM usuarios WHERE email = ?";

            if ($id > 0) {
                $sql_email .= " AND id <> ?";
                $stmt = $conn->prepare($sql_email);
                $stmt->bind_param("si", $email, $id);
            } else {
                $stmt = $conn->prepare($sql_email);
                $stmt->bind_param("s", $email);
            }

            $stmt->execute();
            $email_duplicado = (int)$stmt->get_result()->fetch_assoc()['t'] > 0;
            $stmt->close();

            if ($email_duplicado) {
                $mensaje = "Ese correo electrónico ya está registrado.";
                $tipo_mensaje = "danger";
            }
        }

        if ($mensaje === '') {
            if ($id === 0) {
                /* Registro nuevo */
                $password_final = $password !== '' ? $password : '123456';

                $sql = "INSERT INTO usuarios
                    (nombre, email, codigo_minsa, password, tipo, subtipo, estado, especialidad_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    "sssssssi",
                    $nombre,
                    $email,
                    $codigo_minsa,
                    $password_final,
                    $tipo,
                    $subtipo,
                    $estado,
                    $especialidad_id
                );

                if ($stmt->execute()) {
                    $mensaje = "Usuario registrado correctamente.";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "No se pudo registrar el usuario. Revisa los datos y la estructura de la base de datos.";
                    $tipo_mensaje = "danger";
                }

                $stmt->close();
            } else {
                $stmt = $conn->prepare("SELECT id FROM usuarios WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $existe_usuario = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$existe_usuario) {
                    $mensaje = "El usuario que intentas editar ya no existe.";
                    $tipo_mensaje = "danger";
                } elseif ($password !== '') {
                    $sql = "UPDATE usuarios
                            SET nombre=?, email=?, codigo_minsa=?, password=?,
                                estado=?, tipo=?, subtipo=?, especialidad_id=?
                            WHERE id=?";

                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param(
                        "sssssssii",
                        $nombre,
                        $email,
                        $codigo_minsa,
                        $password,
                        $estado,
                        $tipo,
                        $subtipo,
                        $especialidad_id,
                        $id
                    );

                    if ($stmt->execute()) {
                        $mensaje = "Usuario actualizado correctamente.";
                        $tipo_mensaje = "success";
                    } else {
                        $mensaje = "No se pudo actualizar el usuario.";
                        $tipo_mensaje = "danger";
                    }

                    $stmt->close();
                } else {
                    $sql = "UPDATE usuarios
                            SET nombre=?, email=?, codigo_minsa=?,
                                estado=?, tipo=?, subtipo=?, especialidad_id=?
                            WHERE id=?";

                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param(
                        "ssssssii",
                        $nombre,
                        $email,
                        $codigo_minsa,
                        $estado,
                        $tipo,
                        $subtipo,
                        $especialidad_id,
                        $id
                    );

                    if ($stmt->execute()) {
                        $mensaje = "Usuario actualizado correctamente.";
                        $tipo_mensaje = "success";
                    } else {
                        $mensaje = "No se pudo actualizar el usuario.";
                        $tipo_mensaje = "danger";
                    }

                    $stmt->close();
                }
            }
        }
    }
}

$usuarios = $conn->query("
    SELECT u.*, e.nombre AS especialidad_nombre
    FROM usuarios u
    LEFT JOIN especialidades e ON u.especialidad_id = e.id
    ORDER BY
        FIELD(u.tipo, 'admin', 'jefe_farmacia', 'farmacia', 'doctor'),
        FIELD(u.subtipo, 'fijo', 'turno'),
        u.nombre ASC
");

if (!$usuarios) {
    die("Error al consultar usuarios: " . htmlspecialchars($conn->error));
}

$total_usuarios = $usuarios->num_rows;

$especialidades = $conn->query("
    SELECT MIN(id) AS id, nombre
    FROM especialidades
    GROUP BY nombre
    ORDER BY nombre
");

if (!$especialidades) {
    die("No se pudieron consultar las especialidades. Verifica la tabla especialidades.");
}

$usuario_editar = null;

if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $id_editar = (int)$_GET['editar'];

    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id_editar);
    $stmt->execute();
    $usuario_editar = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$usuario_editar) {
        $mensaje = "No se encontró el usuario que deseas editar.";
        $tipo_mensaje = "danger";
    }
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<style>
:root {
    --us-primary: #dc2626;
    --us-primary-dark: #7f1d1d;
}

.us-hero {
    background: linear-gradient(135deg,#7f1d1d 0%,#dc2626 55%,#ef4444 100%);
    border-radius: 24px;
    padding: 32px 36px;
    color: #fff;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 50px rgba(220,38,38,.25);
    margin-bottom: 24px;
}

.us-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: radial-gradient(circle,rgba(255,255,255,.12) 1px,transparent 1px);
    background-size: 20px 20px;
}

.us-hero::after {
    content: '\f0c0';
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    position: absolute;
    right: -20px;
    bottom: -40px;
    font-size: 200px;
    opacity: .08;
}

.us-hero-content {
    position: relative;
    z-index: 1;
}

.us-hero-icon {
    width: 68px;
    height: 68px;
    background: rgba(255,255,255,.2);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.7rem;
    flex-shrink: 0;
}

.us-hero h2 {
    font-size: 1.7rem;
    font-weight: 700;
    margin: 0 0 4px;
}

.us-hero p {
    margin: 0;
    opacity: .9;
    font-size: .92rem;
}

.us-table-card {
    background: #fff;
    border-radius: 20px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 6px 22px rgba(15,23,42,.05);
    overflow: hidden;
}

.us-table-header {
    padding: 20px 24px;
    background: linear-gradient(180deg,#fafbfc,#f8fafc);
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.us-table-header h6 {
    margin: 0;
    font-weight: 700;
    color: #0f172a;
    font-size: .95rem;
}

.us-count {
    background: var(--us-primary);
    color: #fff;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: .75rem;
    font-weight: 700;
}

.us-search {
    position: relative;
    max-width: 280px;
}

.us-search input {
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    padding: 9px 14px 9px 38px;
    font-size: .85rem;
    width: 100%;
    background: #f8fafc;
}

.us-search::before {
    content: '\f002';
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
}

.us-table {
    margin: 0;
    font-size: .87rem;
}

.us-table thead th {
    background: #f8fafc;
    font-size: .7rem;
    text-transform: uppercase;
    letter-spacing: .8px;
    font-weight: 700;
    color: #64748b;
    padding: 14px 18px;
    border-bottom: 1px solid #e2e8f0;
}

.us-table tbody td {
    padding: 14px 18px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}

.us-table tbody tr:hover {
    background: rgba(220,38,38,.03);
}

.us-avatar {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 700;
    font-size: .85rem;
    flex-shrink: 0;
}

.us-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: .73rem;
    font-weight: 700;
}

.us-badge-admin {
    background: #1e293b;
    color: #fff;
}

.us-badge-jefe {
    background: #dcfce7;
    color: #166534;
}

.us-badge-farmacia {
    background: #ede9fe;
    color: #6d28d9;
}

.us-badge-doctor {
    background: #dbeafe;
    color: #1e40af;
}

.us-badge-turno {
    background: #fef3c7;
    color: #92400e;
}

.us-badge-activo {
    background: #dcfce7;
    color: #166534;
}

.us-badge-inactivo {
    background: #e2e8f0;
    color: #475569;
}

.us-badge-esp {
    background: #d1fae5;
    color: #065f46;
}

.us-action-btn {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: .85rem;
    cursor: pointer;
    transition: all .2s;
}

.us-btn-edit {
    background: #fef3c7;
    color: #b45309;
}

.us-btn-edit:hover {
    background: #fcd34d;
}

.us-btn-delete {
    background: #fee2e2;
    color: #b91c1c;
}

.us-btn-delete:hover {
    background: #fecaca;
}

.us-alert {
    border: none;
    border-radius: 14px;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
    margin-bottom: 20px;
}

.us-alert-success {
    background: #d1fae5;
    color: #065f46;
}

.us-alert-danger {
    background: #fee2e2;
    color: #991b1b;
}

.us-modal-label {
    font-size: .82rem;
    font-weight: 600;
    color: #475569;
    margin-bottom: 6px;
}

#modalUsuario .form-control,
#modalUsuario .form-select {
    border: 1px solid #e2e8f0;
    padding: 10px 14px;
    font-size: .9rem;
    background: #fafbfc;
}

#modalUsuario .form-control:focus,
#modalUsuario .form-select:focus {
    border-color: var(--us-primary);
    box-shadow: 0 0 0 4px rgba(220,38,38,.12);
    background: #fff;
}

.us-toggle {
    width: 48px;
    height: 26px;
    border: none;
    border-radius: 30px;
    padding: 3px;
    background: #9ca3af;
    display: inline-flex;
    align-items: center;
    justify-content: flex-start;
    cursor: pointer;
    transition: background .25s ease;
    flex-shrink: 0;
}

.us-toggle.is-active {
    background: #16a34a;
    justify-content: flex-end;
}

.us-toggle-knob {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 4px rgba(0, 0, 0, .2);
    transition: transform .25s ease;
}

.us-toggle:focus-visible {
    outline: 3px solid rgba(220, 38, 38, .3);
    outline-offset: 3px;
}

@media(max-width:768px) {
    .us-hero {
        padding: 22px;
    }

    .us-hero h2 {
        font-size: 1.3rem;
    }
}
</style>

<div class="container-fluid px-4 py-3">

    <div class="us-hero">
        <div class="us-hero-content d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center">
                <div class="us-hero-icon me-3">
                    <i class="fas fa-users-cog"></i>
                </div>
                <div>
                    <h2>Gestión de Usuarios</h2>
                    <p>
                        <i class="fas fa-user-shield me-2"></i>
                        Administración del personal del sistema
                    </p>
                </div>
            </div>

            <a href="dashboard.php" class="btn btn-light rounded-pill px-4 fw-semibold">
                <i class="fas fa-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <?php if ($mensaje !== ''): ?>
        <div class="us-alert us-alert-<?php echo $tipo_mensaje === 'success' ? 'success' : 'danger'; ?>">
            <i class="fas fa-<?php echo $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            <div><?php echo htmlspecialchars($mensaje); ?></div>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-end mb-3">
        <button
            type="button"
            class="btn btn-danger rounded-pill px-4 py-3 fw-semibold"
            onclick="abrirRegistro()">
            <i class="fas fa-user-plus me-2"></i>Registrar usuario
        </button>
    </div>

    <div class="us-table-card">
        <div class="us-table-header">
            <h6>
                <i class="fas fa-list me-2 text-danger"></i>
                Usuarios Registrados
            </h6>

            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="us-search">
                    <input type="text" id="buscar" placeholder="Buscar usuario...">
                </div>

                <span class="us-count">
                    <?php echo $total_usuarios; ?> usuarios
                </span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table us-table" id="tablaUsuarios">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Código MINSA</th>
                        <th>Rol</th>
                        <th>Especialidad</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($usuarios->num_rows > 0): ?>

                    <?php while ($u = $usuarios->fetch_assoc()):
                        $es_turno = (
                            $u['tipo'] === 'doctor' &&
                            ($u['subtipo'] ?? 'fijo') === 'turno'
                        );

                        $inicial = strtoupper(substr($u['nombre'], 0, 2));
                        $usuario_activo = ($u['estado'] === 'activo');
                    ?>

                        <tr>
                            <td>
                                <span class="badge bg-light text-secondary rounded-pill">
                                    #<?php echo (int)$u['id']; ?>
                                </span>
                            </td>

                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div
                                        class="us-avatar"
                                        style="background:#<?php echo substr(md5($u['nombre']), 0, 6); ?>">
                                        <?php echo htmlspecialchars($inicial); ?>
                                    </div>

                                    <div class="fw-bold text-dark">
                                        <?php echo htmlspecialchars($u['nombre']); ?>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($u['email']); ?>
                                </small>
                            </td>

                            <td>
                                <?php if (!empty($u['codigo_minsa'])): ?>
                                    <span class="us-badge us-badge-doctor">
                                        <?php echo htmlspecialchars($u['codigo_minsa']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($u['tipo'] === 'admin'): ?>

                                    <span class="us-badge us-badge-admin">
                                        <i class="fas fa-user-shield"></i> Admin
                                    </span>

                                <?php elseif ($u['tipo'] === 'jefe_farmacia'): ?>

                                    <span class="us-badge us-badge-jefe">
                                        <i class="fas fa-user-tie"></i> Jefe Farmacia
                                    </span>

                                <?php elseif ($u['tipo'] === 'farmacia'): ?>

                                    <span class="us-badge us-badge-farmacia">
                                        <i class="fas fa-user-nurse"></i> Farmacia
                                    </span>

                                <?php elseif ($es_turno): ?>

                                    <span class="us-badge us-badge-turno">
                                        <i class="fas fa-clock"></i> Doctor Turno
                                    </span>

                                <?php else: ?>

                                    <span class="us-badge us-badge-doctor">
                                        <i class="fas fa-user-md"></i> Doctor
                                    </span>

                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (!empty($u['especialidad_nombre'])): ?>
                                    <span class="us-badge us-badge-esp">
                                        <i class="fas fa-stethoscope"></i>
                                        <?php echo htmlspecialchars($u['especialidad_nombre']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Estado e interruptor -->
                            <td>
                                <div class="d-flex align-items-center gap-2 flex-wrap">

                                    <span class="us-badge us-badge-<?php echo $usuario_activo ? 'activo' : 'inactivo'; ?>">
                                        <?php echo $usuario_activo ? 'Activo' : 'Inactivo'; ?>
                                    </span>

                                    <button
                                        type="button"
                                        class="us-toggle <?php echo $usuario_activo ? 'is-active' : ''; ?>"
                                        title="<?php echo $usuario_activo ? 'Desactivar usuario' : 'Activar usuario'; ?>"
                                        aria-label="<?php echo $usuario_activo ? 'Desactivar usuario' : 'Activar usuario'; ?>"
                                        onclick="confirmarCambioEstado(
                                            <?php echo (int)$u['id']; ?>,
                                            '<?php echo $usuario_activo ? 'inactivo' : 'activo'; ?>',
                                            <?php echo htmlspecialchars(
                                                json_encode(
                                                    $u['nombre'],
                                                    JSON_HEX_APOS | JSON_HEX_QUOT |
                                                    JSON_HEX_TAG | JSON_HEX_AMP
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                        )">
                                        <span class="us-toggle-knob"></span>
                                    </button>

                                </div>
                            </td>

                            <td class="text-end text-nowrap">
                                <a
                                    href="usuarios.php?editar=<?php echo (int)$u['id']; ?>"
                                    class="us-action-btn us-btn-edit me-1"
                                    title="Editar">
                                    <i class="fas fa-pen"></i>
                                </a>

                                <button
                                    type="button"
                                    class="us-action-btn us-btn-delete"
                                    title="Eliminar"
                                    onclick="eliminarUsuario(
                                        <?php echo (int)$u['id']; ?>,
                                        <?php echo htmlspecialchars(
                                            json_encode(
                                                $u['nombre'],
                                                JSON_HEX_APOS | JSON_HEX_QUOT |
                                                JSON_HEX_TAG | JSON_HEX_AMP
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                    )">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="fas fa-users-slash fa-3x mb-3 d-block opacity-50"></i>
                            <h6 class="fw-bold">No hay usuarios registrados</h6>
                            <p class="small mb-0">
                                Pulsa «Registrar usuario» para agregar el primero.
                            </p>
                        </td>
                    </tr>

                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div
    class="modal fade"
    id="modalUsuario"
    tabindex="-1"
    aria-labelledby="tituloModalUsuario"
    aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content border-0 rounded-4 shadow">

            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold" id="tituloModalUsuario">
                    <i class="fas fa-user-plus me-2"></i>Registrar usuario
                </h5>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                    aria-label="Cerrar">
                </button>
            </div>

            <div class="modal-body p-4">
                <form method="POST" action="usuarios.php" id="formUsuario">

                    <input
                        type="hidden"
                        name="id"
                        id="usuario_id"
                        value="<?php echo $usuario_editar ? (int)$usuario_editar['id'] : ''; ?>">

                    <div class="mb-3">
                        <label class="form-label us-modal-label">
                            Nombre completo *
                        </label>

                        <input
                            type="text"
                            class="form-control rounded-pill"
                            name="nombre"
                            id="usuario_nombre"
                            required
                            value="<?php echo htmlspecialchars($usuario_editar['nombre'] ?? ''); ?>"
                            placeholder="Ej: Dr. Juan Pérez">
                    </div>

                    <div class="mb-3">
                        <label class="form-label us-modal-label">Email *</label>

                        <input
                            type="email"
                            class="form-control rounded-pill"
                            name="email"
                            id="usuario_email"
                            required
                            value="<?php echo htmlspecialchars($usuario_editar['email'] ?? ''); ?>"
                            placeholder="usuario@centro.com">
                    </div>

                    <div class="mb-3">
                        <label class="form-label us-modal-label">Código MINSA</label>

                        <input
                            type="text"
                            class="form-control rounded-pill"
                            name="codigo_minsa"
                            id="usuario_codigo_minsa"
                            value="<?php echo htmlspecialchars($usuario_editar['codigo_minsa'] ?? ''); ?>"
                            placeholder="Ej: 12345"
                            maxlength="50">

                        <small class="text-muted">
                            Obligatorio para doctores.
                        </small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label us-modal-label">Contraseña</label>

                        <input
                            type="password"
                            class="form-control rounded-pill"
                            name="password"
                            id="usuario_password"
                            autocomplete="new-password"
                            placeholder="Ingresa una contraseña">

                        <small class="text-muted" id="ayuda_password">
                            <?php echo $usuario_editar
                                ? 'Déjala vacía para conservar la contraseña actual.'
                                : 'Si se deja vacía, se utilizará 123456.'; ?>
                        </small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label us-modal-label">
                            Tipo de usuario *
                        </label>

                        <select
                            name="tipo"
                            id="tipo"
                            class="form-select rounded-pill"
                            required>

                            <?php $tipo_actual = $usuario_editar['tipo'] ?? 'doctor'; ?>

                            <option value="doctor" <?php echo $tipo_actual === 'doctor' ? 'selected' : ''; ?>>
                                Doctor
                            </option>

                            <option value="jefe_farmacia" <?php echo $tipo_actual === 'jefe_farmacia' ? 'selected' : ''; ?>>
                                Jefe de Farmacia
                            </option>

                            <option value="farmacia" <?php echo $tipo_actual === 'farmacia' ? 'selected' : ''; ?>>
                                Personal de Farmacia
                            </option>

                            <option value="admin" <?php echo $tipo_actual === 'admin' ? 'selected' : ''; ?>>
                                Administrador
                            </option>
                        </select>
                    </div>

                    <div class="mb-3" id="bloque_subtipo">
                        <label class="form-label us-modal-label">
                            Tipo de doctor *
                        </label>

                        <select
                            name="subtipo"
                            id="usuario_subtipo"
                            class="form-select rounded-pill">

                            <?php $subtipo_actual = $usuario_editar['subtipo'] ?? 'fijo'; ?>

                            <option value="fijo" <?php echo $subtipo_actual === 'fijo' ? 'selected' : ''; ?>>
                                Doctor fijo (permanente)
                            </option>

                            <option value="turno" <?php echo $subtipo_actual === 'turno' ? 'selected' : ''; ?>>
                                Doctor de turno (temporal)
                            </option>
                        </select>
                    </div>

                    <div class="mb-3" id="bloque_especialidad">
                        <label class="form-label us-modal-label">
                            Especialidad
                        </label>

                        <select
                            name="especialidad_id"
                            id="usuario_especialidad"
                            class="form-select rounded-pill">

                            <option value="">Sin especialidad</option>

                            <?php
                            $especialidades_modal = $conn->query("
                                SELECT MIN(id) AS id, nombre
                                FROM especialidades
                                GROUP BY nombre
                                ORDER BY nombre
                            ");

                            if ($especialidades_modal):
                                while ($esp = $especialidades_modal->fetch_assoc()):
                            ?>

                                <option
                                    value="<?php echo (int)$esp['id']; ?>"
                                    <?php echo (
                                        isset($usuario_editar['especialidad_id']) &&
                                        (int)$usuario_editar['especialidad_id'] === (int)$esp['id']
                                    ) ? 'selected' : ''; ?>>

                                    <?php echo htmlspecialchars($esp['nombre']); ?>

                                </option>

                            <?php
                                endwhile;
                            endif;
                            ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label us-modal-label">Estado *</label>

                        <select
                            name="estado"
                            id="usuario_estado"
                            class="form-select rounded-pill"
                            required>

                            <option
                                value="activo"
                                <?php echo ($usuario_editar['estado'] ?? 'activo') === 'activo' ? 'selected' : ''; ?>>
                                Activo
                            </option>

                            <option
                                value="inactivo"
                                <?php echo ($usuario_editar['estado'] ?? '') === 'inactivo' ? 'selected' : ''; ?>>
                                Inactivo
                            </option>
                        </select>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button
                            type="button"
                            class="btn btn-outline-secondary rounded-pill px-4"
                            data-bs-dismiss="modal">
                            Cancelar
                        </button>

                        <button
                            type="submit"
                            name="guardar_usuario"
                            class="btn btn-danger rounded-pill px-4">

                            <i class="fas fa-save me-2"></i>

                            <?php echo $usuario_editar
                                ? 'Actualizar usuario'
                                : 'Guardar usuario'; ?>
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function actualizarCamposUsuario() {
    const tipo = document.getElementById('tipo');
    const esDoctor = tipo.value === 'doctor';

    document.getElementById('bloque_subtipo').style.display =
        esDoctor ? '' : 'none';

    document.getElementById('bloque_especialidad').style.display =
        esDoctor ? '' : 'none';

    document.getElementById('usuario_codigo_minsa').required = esDoctor;
}
function abrirRegistro() {
    const form = document.getElementById('formUsuario');
    form.reset();

    document.getElementById('usuario_id').value = '';
    document.getElementById('usuario_nombre').value = '';
    document.getElementById('usuario_email').value = '';
    document.getElementById('usuario_codigo_minsa').value = '';
    document.getElementById('usuario_password').value = '';
    document.getElementById('usuario_password').required = false;
    document.getElementById('tipo').value = 'doctor';
    document.getElementById('usuario_subtipo').value = 'fijo';
    document.getElementById('usuario_especialidad').value = '';
    document.getElementById('usuario_estado').value = 'activo';

    document.getElementById('tituloModalUsuario').innerHTML =
        '<i class="fas fa-user-plus me-2"></i>Registrar usuario';

    document.getElementById('ayuda_password').textContent =
        'Si se deja vacía, se utilizará 123456.';

    actualizarCamposUsuario();

    bootstrap.Modal.getOrCreateInstance(
        document.getElementById('modalUsuario')
    ).show();
}

function confirmarCambioEstado(id, nuevoEstado, nombre) {
    const activar = nuevoEstado === 'activo';

    const nombreSeguro = String(nombre).replace(/[&<>"']/g, function(c) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[c];
    });

    Swal.fire({
        title: activar
            ? '¿Activar a este usuario?'
            : '¿Desactivar a este usuario?',

        html: activar
            ? '¿Estás seguro de activar a <strong>' +
              nombreSeguro + '</strong>?'
            : '¿Estás seguro de desactivar a <strong>' +
              nombreSeguro +
              '</strong>?<br><br>El usuario quedará marcado como inactivo.',

        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: activar ? '#16a34a' : '#dc2626',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: activar ? 'Sí, activar' : 'Sí, desactivar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true
    }).then(function(result) {
        if (result.isConfirmed) {
            const formulario = document.createElement('form');
            formulario.method = 'POST';
            formulario.action = 'usuarios.php';

            const campos = {
                cambiar_estado_usuario: '1',
                usuario_id_estado: String(id),
                nuevo_estado: nuevoEstado
            };

            Object.keys(campos).forEach(function(nombreCampo) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = nombreCampo;
                input.value = campos[nombreCampo];
                formulario.appendChild(input);
            });

            document.body.appendChild(formulario);
            formulario.submit();
        }
    });
}

/* Confirmar eliminación de usuario */
function eliminarUsuario(id, nombre) {
    Swal.fire({
        title: '¿Eliminar usuario?',
        html: '¿Estás seguro de eliminar a <strong>' +
              String(nombre).replace(/[&<>"']/g, function(c) {
                  return {
                      '&': '&amp;',
                      '<': '&lt;',
                      '>': '&gt;',
                      '"': '&quot;',
                      "'": '&#039;'
                  }[c];
              }) +
              '</strong>?<br><br><small class="text-danger">Esta acción no se puede deshacer.</small>',

        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(function(result) {
        if (result.isConfirmed) {
            window.location.href =
                'usuarios.php?eliminar=' + encodeURIComponent(id);
        }
    });
}
document.addEventListener('DOMContentLoaded', function() {
    const tipo = document.getElementById('tipo');

    tipo.addEventListener('change', actualizarCamposUsuario);
    actualizarCamposUsuario();
    <?php if ($usuario_editar): ?>

    document.getElementById('tituloModalUsuario').innerHTML =
        '<i class="fas fa-pen me-2"></i>Editar usuario';

    document.getElementById('ayuda_password').textContent =
        'Déjala vacía para conservar la contraseña actual.';

    bootstrap.Modal.getOrCreateInstance(
        document.getElementById('modalUsuario')
    ).show();

    <?php endif; ?>
    const inputBuscar = document.getElementById('buscar');

    if (inputBuscar) {
        inputBuscar.addEventListener('input', function() {
            const filtro = this.value.toLowerCase();

            const filas = document.querySelectorAll(
                '#tablaUsuarios tbody tr'
            );

            filas.forEach(function(fila) {
                fila.style.display =
                    fila.textContent.toLowerCase().includes(filtro)
                        ? ''
                        : 'none';
            });
        });
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>