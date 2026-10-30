<?php
session_save_path('/tmp'); // Esto es clave para Vercel
    session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header('Location: ../../login.php');
    exit();
}
$mensaje = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $especialidad_id = intval($_POST['especialidad_id']);
        $estado = $_POST['estado'] ?? 'activo';
        if (empty($nombre) || empty($email) || empty($password) || $especialidad_id <= 0) {
            $error = 'Todos los campos son obligatorios.';
        } else {
            $check = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $res = $check->get_result();
            if ($res->num_rows > 0) {
                $error = 'El correo electrónico ya está registrado.';
            } else {
                $sql = "INSERT INTO usuarios (nombre, email, password, tipo, especialidad_id, estado) 
                        VALUES (?, ?, ?, 'doctor', ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssis", $nombre, $email, $password, $especialidad_id, $estado);
                if ($stmt->execute()) {
                    $mensaje = 'Doctor registrado exitosamente.';
                } else {
                    $error = 'Error al registrar el doctor: ' . $conn->error;
                }
            }
        }
    } elseif ($accion === 'editar') {
        $id = intval($_POST['id']);
        $nombre = trim($_POST['nombre']);
        $email = trim($_POST['email']);
        $especialidad_id = intval($_POST['especialidad_id']);
        $estado = $_POST['estado'] ?? 'activo';
        $password = trim($_POST['password']);

        if ($id <= 0 || empty($nombre) || empty($email) || $especialidad_id <= 0) {
            $error = 'Todos los campos son obligatorios.';
        } else {
            $check = $conn->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $check->bind_param("si", $email, $id);
            $check->execute();
            $res = $check->get_result();
            if ($res->num_rows > 0) {
                $error = 'El correo electrónico ya está registrado por otro usuario.';
            } else {
                if (!empty($password)) {
                    $sql = "UPDATE usuarios SET nombre=?, email=?, password=?, especialidad_id=?, estado=? WHERE id=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssisi", $nombre, $email, $password, $especialidad_id, $estado, $id);
                } else {
                    $sql = "UPDATE usuarios SET nombre=?, email=?, especialidad_id=?, estado=? WHERE id=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssisi", $nombre, $email, $especialidad_id, $estado, $id);
                }
                if ($stmt->execute()) {
                    $mensaje = 'Doctor actualizado correctamente.';
                } else {
                    $error = 'Error al actualizar: ' . $conn->error;
                }
            }
        }
    } elseif ($accion === 'eliminar') {
        $id = intval($_POST['id']);
        if ($id > 0) {
            $sql = "UPDATE usuarios SET estado = 'inactivo' WHERE id = ? AND tipo = 'doctor'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $mensaje = 'Doctor desactivado correctamente.';
            } else {
                $error = 'Error al desactivar: ' . $conn->error;
            }
        }
    }
}
$doctores = [];
$sql_doctores = "SELECT u.*, e.nombre AS especialidad 
                 FROM usuarios u
                 LEFT JOIN especialidades e ON u.especialidad_id = e.id
                 WHERE u.tipo = 'doctor'
                 ORDER BY u.nombre ASC";
$result = $conn->query($sql_doctores);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $doctores[] = $row;
    }
}
$especialidades = [];
$res_esp = $conn->query("SELECT id, nombre FROM especialidades WHERE estado = 'activo' ORDER BY nombre");
if ($res_esp) {
    while ($row = $res_esp->fetch_assoc()) {
        $especialidades[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Doctores - Trazabilidad</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .glass-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
        }
        .avatar-sm {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #fff;
            font-size: 14px;
            flex-shrink: 0;
            background: #0d6efd;
        }
        .table tbody tr {
            transition: background 0.15s;
        }
        .table tbody tr:hover {
            background: rgba(13, 110, 253, 0.04);
        }
        .badge-estado {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-activo { background: #d4edda; color: #155724; }
        .badge-inactivo { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container-fluid px-4 py-4">
        <div class="row">
            <div class="col-12">
                <div class="glass-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 class="fw-bold mb-0">
                            <i class="fas fa-user-md text-primary me-2"></i>
                            Gestión de Doctores
                        </h3>
                        <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modalDoctor" onclick="resetForm()">
                            <i class="fas fa-plus me-2"></i>Nuevo Doctor
                        </button>
                    </div>

                    <?php if ($mensaje): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> <?php echo $mensaje; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th>#</th>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Especialidad</th>
                                    <th>Estado</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($doctores) > 0): ?>
                                    <?php foreach ($doctores as $doc): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-secondary-subtle text-secondary rounded-pill">#<?php echo $doc['id']; ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm me-2" style="background: <?php echo '#' . substr(md5($doc['nombre']), 0, 6); ?>;">
                                                        <?php echo strtoupper(substr($doc['nombre'], 0, 2)); ?>
                                                    </div>
                                                    <?php echo htmlspecialchars($doc['nombre']); ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($doc['email']); ?></td>
                                            <td>
                                                <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2">
                                                    <?php echo htmlspecialchars($doc['especialidad'] ?? 'Sin asignar'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge-estado <?php echo ($doc['estado'] == 'activo') ? 'badge-activo' : 'badge-inactivo'; ?>">
                                                    <?php echo ucfirst($doc['estado']); ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1" 
                                                        onclick="editarDoctor(<?php echo htmlspecialchars(json_encode($doc)); ?>)">
                                                    <i class="fas fa-edit me-1"></i> Editar
                                                </button>
                                                <?php if ($doc['estado'] == 'activo'): ?>
                                                    <button class="btn btn-sm btn-outline-danger rounded-pill px-3" 
                                                            onclick="eliminarDoctor(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['nombre']); ?>')">
                                                        <i class="fas fa-user-slash me-1"></i> Desactivar
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted"><small>Inactivo</small></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="fas fa-user-md fa-3x mb-3 d-block opacity-25"></i>
                                            No hay doctores registrados. ¡Crea el primero!
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
    <div class="modal fade" id="modalDoctor" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content glass-card">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalTitle">
                        <i class="fas fa-user-plus text-primary me-2"></i>Nuevo Doctor
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="accion" id="accion" value="crear">
                        <input type="hidden" name="id" id="editId" value="0">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Nombre completo</label>
                                <input type="text" class="form-control" name="nombre" id="nombre" required placeholder="Dr. Juan Pérez">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Correo electrónico</label>
                                <input type="email" class="form-control" name="email" id="email" required placeholder="doctor@farmacia.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Contraseña</label>
                                <input type="password" class="form-control" name="password" id="password" placeholder="Mínimo 6 caracteres">
                                <small class="text-muted">Dejar en blanco para no cambiar (edición).</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Especialidad</label>
                                <select class="form-select" name="especialidad_id" id="especialidad_id" required>
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($especialidades as $esp): ?>
                                        <option value="<?php echo $esp['id']; ?>"><?php echo htmlspecialchars($esp['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Estado</label>
                                <select class="form-select" name="estado" id="estado">
                                    <option value="activo">Activo</option>
                                    <option value="inactivo">Inactivo</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                            <i class="fas fa-save me-2"></i>Guardar Doctor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const modal = new bootstrap.Modal(document.getElementById('modalDoctor'));

        function resetForm() {
            document.getElementById('accion').value = 'crear';
            document.getElementById('editId').value = '0';
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus text-primary me-2"></i>Nuevo Doctor';
            document.getElementById('nombre').value = '';
            document.getElementById('email').value = '';
            document.getElementById('password').value = '';
            document.getElementById('especialidad_id').value = '';
            document.getElementById('estado').value = 'activo';
            document.getElementById('password').placeholder = 'Mínimo 6 caracteres';
        }

        function editarDoctor(doc) {
            document.getElementById('accion').value = 'editar';
            document.getElementById('editId').value = doc.id;
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-edit text-primary me-2"></i>Editar Doctor';
            document.getElementById('nombre').value = doc.nombre;
            document.getElementById('email').value = doc.email;
            document.getElementById('password').value = '';
            document.getElementById('password').placeholder = 'Dejar en blanco para no cambiar';
            document.getElementById('especialidad_id').value = doc.especialidad_id || '';
            document.getElementById('estado').value = doc.estado;
            modal.show();
        }

        function eliminarDoctor(id, nombre) {
            if (confirm(`¿Estás seguro de desactivar al doctor "${nombre}"? El usuario no podrá iniciar sesión.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        <?php if ($error && isset($_POST['accion']) && $_POST['accion'] === 'crear'): ?>
        <?php endif; ?>
    </script>
</body>
</html>