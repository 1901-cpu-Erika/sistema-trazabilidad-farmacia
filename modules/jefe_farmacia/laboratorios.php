<?php
if (session_status() === PHP_SESSION_NONE) {
    session_save_path('/tmp');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once '../../config/database.php';

if (isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $pais = $conn->real_escape_string($_POST['pais']);
    $estado = isset($_POST['estado']) ? 1 : 0;

    if ($accion == 'crear') {
        $sql = "INSERT INTO laboratorios (nombre, pais, estado) VALUES ('$nombre', '$pais', $estado)";
        if ($conn->query($sql)) {
            $_SESSION['mensaje'] = "Laboratorio creado exitosamente";
            $_SESSION['tipo_mensaje'] = "success";
        } else {
            $_SESSION['mensaje'] = "Error al crear el laboratorio";
            $_SESSION['tipo_mensaje'] = "error";
        }
    } elseif ($accion == 'editar') {
        $id = intval($_POST['id']);
        $sql = "UPDATE laboratorios SET nombre = '$nombre', pais = '$pais', estado = $estado WHERE id = $id";
        if ($conn->query($sql)) {
            $_SESSION['mensaje'] = "Laboratorio actualizado exitosamente";
            $_SESSION['tipo_mensaje'] = "success";
        } else {
            $_SESSION['mensaje'] = "Error al actualizar el laboratorio";
            $_SESSION['tipo_mensaje'] = "error";
        }
    }
    
    header("Location: laboratorios.php");
    exit();
}

if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    $sql = "DELETE FROM laboratorios WHERE id = $id";
    if ($conn->query($sql)) {
        $_SESSION['mensaje'] = "Laboratorio eliminado exitosamente";
        $_SESSION['tipo_mensaje'] = "success";
    } else {
        $_SESSION['mensaje'] = "Error al eliminar. Verifique si tiene medicamentos asociados.";
        $_SESSION['tipo_mensaje'] = "error";
    }
    header("Location: laboratorios.php");
    exit();
}

$titulo_pagina = "Gestión de Laboratorios - Farmacia";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$laboratorios = $conn->query("SELECT * FROM laboratorios ORDER BY nombre ASC");
?>

<style>
    .main-content { padding-top: 0 !important; }
    .banner-container { margin-top: 10px; margin-bottom: 15px; padding: 0 20px; }
    .dashboard-container { padding: 0 20px 20px 20px; margin-top: 0; }
    
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
    .btn-gradient-blue:hover { color: #fff; opacity: 0.9; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(30,60,114,0.4); }

    .badge-soft-success { background: #d4edda; color: #155724; padding: 8px 12px; border-radius: 8px; font-weight: 600; }
    .badge-soft-danger { background: #f8d7da; color: #721c24; padding: 8px 12px; border-radius: 8px; font-weight: 600; }
    
    .btn-action-edit { background: #0dcaf0; color: white; border: none; border-radius: 50%; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s; }
    .btn-action-delete { background: #dc3545; color: white; border: none; border-radius: 50%; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s; }
    .btn-action-edit:hover { background: #0bacce; color: white; transform: scale(1.1); }
    .btn-action-delete:hover { background: #c82333; color: white; transform: scale(1.1); }
</style>

<div class="container-fluid banner-container">
    <div class="card border-0 shadow-lg rounded-4 overflow-hidden position-relative" 
         style="background: linear-gradient(135deg, #1a3a5c 0%, #2d6a9f 100%);">
        <i class="fas fa-industry position-absolute" style="font-size: 150px; right: 20px; top: -30px; opacity: 0.1; color: white;"></i>
        <i class="fas fa-flask position-absolute" style="font-size: 100px; right: 180px; bottom: -30px; opacity: 0.1; color: white;"></i>

        <div class="card-body p-4 text-white position-relative">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-white bg-opacity-25 p-3 me-3 shadow">
                    <i class="fas fa-industry fa-2x text-white"></i>
                </div>
                <div>
                    <h2 class="display-6 fw-bold mb-1" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.3);">
                        Gestión de Laboratorios
                    </h2>
                    <p class="mb-0 opacity-75">
                        <i class="fas fa-info-circle me-2"></i>
                        Administra el catálogo de laboratorios farmacéuticos
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid dashboard-container fade-in">
    <div class="row">
        <div class="col-12">
            <div class="card custom-card h-100">
                <div class="card-header custom-card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-list me-2" style="color: #1e3c72;"></i>Listado de Laboratorios</span>
                    <button class="btn btn-gradient-blue btn-sm rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modalLaboratorio" onclick="prepararCrear()">
                        <i class="fas fa-plus me-1"></i> Nuevo Laboratorio
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">ID</th>
                                    <th>Nombre del Laboratorio</th>
                                    <th>País</th>
                                    <th>Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($laboratorios->num_rows > 0): ?>
                                    <?php while($lab = $laboratorios->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-muted">#<?php echo $lab['id']; ?></td>
                                        <td class="fw-bold text-dark"><?php echo $lab['nombre']; ?></td>
                                        <td class="text-secondary"><?php echo !empty($lab['pais']) ? $lab['pais'] : 'No especificado'; ?></td>
                                        <td>
                                            <?php if($lab['estado'] == 1): ?>
                                                <span class="badge badge-soft-success"><i class="fas fa-check-circle me-1"></i> Activo</span>
                                            <?php else: ?>
                                                <span class="badge badge-soft-danger"><i class="fas fa-times-circle me-1"></i> Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-action-edit me-2" onclick="prepararEditar(<?php echo $lab['id']; ?>, '<?php echo addslashes($lab['nombre']); ?>', '<?php echo addslashes($lab['pais']); ?>', <?php echo $lab['estado']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-action-delete" onclick="confirmarEliminar(<?php echo $lab['id']; ?>)">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="fas fa-industry fa-3x mb-3 text-light"></i>
                                            <p>No hay laboratorios registrados.</p>
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
</div>

<div class="modal fade" id="modalLaboratorio" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header border-0" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);">
                <h5 class="modal-title text-white fw-bold" id="modalTitle">Nuevo Laboratorio</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="laboratorios.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="accion" id="accion" value="crear">
                    <input type="hidden" name="id" id="lab_id" value="">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small">Nombre del Laboratorio</label>
                        <input type="text" class="form-control" name="nombre" id="lab_nombre" required placeholder="Ej. Bayer, Genfar, etc.">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small">País</label>
                        <input type="text" class="form-control" name="pais" id="lab_pais" placeholder="Ej. Alemania, EE.UU., etc.">
                    </div>
                    
                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" name="estado" id="lab_estado" checked>
                        <label class="form-check-label fw-bold text-muted" for="lab_estado">Laboratorio Activo</label>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gradient-blue rounded-pill px-4">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
<?php if(isset($_SESSION['mensaje'])): ?>
    Swal.fire({
        icon: '<?php echo $_SESSION['tipo_mensaje']; ?>',
        title: '<?php echo $_SESSION['tipo_mensaje'] == 'success' ? '¡Éxito!' : 'Error'; ?>',
        text: '<?php echo $_SESSION['mensaje']; ?>',
        confirmButtonColor: '#1e3c72'
    });
    <?php unset($_SESSION['mensaje']); unset($_SESSION['tipo_mensaje']); ?>
<?php endif; ?>

function prepararCrear() {
    document.getElementById('modalTitle').innerText = 'Nuevo Laboratorio';
    document.getElementById('accion').value = 'crear';
    document.getElementById('lab_id').value = '';
    document.getElementById('lab_nombre').value = '';
    document.getElementById('lab_pais').value = '';
    document.getElementById('lab_estado').checked = true;
}

function prepararEditar(id, nombre, pais, estado) {
    document.getElementById('modalTitle').innerText = 'Editar Laboratorio';
    document.getElementById('accion').value = 'editar';
    document.getElementById('lab_id').value = id;
    document.getElementById('lab_nombre').value = nombre;
    document.getElementById('lab_pais').value = pais;
    document.getElementById('lab_estado').checked = estado == 1;
    
    var myModal = new bootstrap.Modal(document.getElementById('modalLaboratorio'));
    myModal.show();
}

function confirmarEliminar(id) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "Esta acción no se puede revertir.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'laboratorios.php?eliminar=' + id;
        }
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>