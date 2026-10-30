<?php
if (session_status() === PHP_SESSION_NONE) session_save_path('/tmp'); // Clave para Vercel
    session_start();
require_once '../../config/database.php';



if ($_SESSION['tipo_usuario'] != 'jefe_farmacia') {
    header("Location: ../../login.php");
    exit();
}
$titulo_pagina = "Gestión de Medicamentos";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';


$mensaje = '';
$tipo_mensaje = '';
$result = $conn->query("SHOW COLUMNS FROM medicamentos LIKE 'estado'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE medicamentos ADD COLUMN estado TINYINT DEFAULT 1");
    $conn->query("UPDATE medicamentos SET estado = 1");
}
$tablas_existen = true;
$error_tablas = '';

$result = $conn->query("SHOW TABLES LIKE 'categorias'");
if ($result->num_rows == 0) {
    $tablas_existen = false;
    $error_tablas .= "La tabla 'categorias' no existe. ";
}

$result = $conn->query("SHOW TABLES LIKE 'laboratorios'");
if ($result->num_rows == 0) {
    $tablas_existen = false;
    $error_tablas .= "La tabla 'laboratorios' no existe. ";
}

if (!$tablas_existen) {
    $mensaje = "Error: " . $error_tablas . " Ejecuta el script SQL para crear las tablas.";
    $tipo_mensaje = "danger";
}
function generarCodigoMinsa($conn, $nombre) {
    // Tomar las primeras 3 letras del nombre en mayúsculas
    $prefijo = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $nombre), 0, 3));
    if (strlen($prefijo) < 3) {
        $prefijo = str_pad($prefijo, 3, 'X');
    }
    $sql = "SELECT codigo_minsa FROM medicamentos 
            WHERE codigo_minsa LIKE '$prefijo-%' 
            ORDER BY codigo_minsa DESC LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $ultimo_codigo = $row['codigo_minsa'];
        $numero = intval(substr($ultimo_codigo, 4)) + 1;
    } else {
        $numero = 1;
    }
    $numero_formateado = str_pad($numero, 3, '0', STR_PAD_LEFT);
    
    return $prefijo . '-' . $numero_formateado;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $tablas_existen) {
    $nombre = trim($_POST['nombre']);
    $codigo_minsa = trim($_POST['codigo_minsa'] ?? '');
    $categoria_id = !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;
    $laboratorio_id = !empty($_POST['laboratorio_id']) ? intval($_POST['laboratorio_id']) : null;

    if (empty($nombre)) {
        $mensaje = "El nombre del medicamento es obligatorio.";
        $tipo_mensaje = "danger";
    } else {
        if ($_POST['accion'] == 'crear') {
            if (empty($codigo_minsa)) {
                $codigo_minsa = generarCodigoMinsa($conn, $nombre);
            }
            
            $sql = "INSERT INTO medicamentos (nombre, codigo_minsa, categoria_id, laboratorio_id, estado) 
                    VALUES (?, ?, ?, ?, 1)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                $mensaje = "Error al preparar la consulta: " . $conn->error;
                $tipo_mensaje = "danger";
            } else {
                $stmt->bind_param("ssii", $nombre, $codigo_minsa, $categoria_id, $laboratorio_id);
                if ($stmt->execute()) {
                    $mensaje = "✅ Medicamento creado correctamente. Código: <strong>" . $codigo_minsa . "</strong>";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al crear: " . $stmt->error;
                    $tipo_mensaje = "danger";
                }
                $stmt->close();
            }
        } elseif ($_POST['accion'] == 'editar' && isset($_POST['id'])) {
            $id = intval($_POST['id']);
            
            if (empty($codigo_minsa)) {
                $sql_get = "SELECT codigo_minsa FROM medicamentos WHERE id = ?";
                $stmt_get = $conn->prepare($sql_get);
                $stmt_get->bind_param("i", $id);
                $stmt_get->execute();
                $result_get = $stmt_get->get_result();
                $row_get = $result_get->fetch_assoc();
                $codigo_minsa = $row_get['codigo_minsa'];
                $stmt_get->close();
            }
            
            $sql = "UPDATE medicamentos SET nombre=?, codigo_minsa=?, categoria_id=?, laboratorio_id=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                $mensaje = "Error al preparar la consulta: " . $conn->error;
                $tipo_mensaje = "danger";
            } else {
                $stmt->bind_param("ssiii", $nombre, $codigo_minsa, $categoria_id, $laboratorio_id, $id);
                if ($stmt->execute()) {
                    $mensaje = "✅ Medicamento actualizado correctamente.";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al actualizar: " . $stmt->error;
                    $tipo_mensaje = "danger";
                }
                $stmt->close();
            }
        }
    }
}
if (isset($_GET['cambiar_estado']) && is_numeric($_GET['cambiar_estado'])) {
    $id = intval($_GET['cambiar_estado']);
    $nuevo_estado = isset($_GET['suspender']) ? 0 : 1;
    $accion_texto = $nuevo_estado == 0 ? 'suspendido' : 'reactivado';
    
    $sql = "UPDATE medicamentos SET estado = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $nuevo_estado, $id);
        if ($stmt->execute()) {
            $mensaje = "✅ Medicamento $accion_texto correctamente.";
            $tipo_mensaje = "success";
        } else {
            $mensaje = "Error al cambiar estado: " . $stmt->error;
            $tipo_mensaje = "danger";
        }
        $stmt->close();
    } else {
        $mensaje = "Error al preparar la consulta.";
        $tipo_mensaje = "danger";
    }
}
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    
    $sql_check = "SELECT COUNT(*) as total FROM lotes WHERE medicamento_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $tiene_lotes = $result_check->fetch_assoc()['total'] > 0;
    $stmt_check->close();

    if ($tiene_lotes) {
        $mensaje = "⚠️ No se puede eliminar este medicamento porque tiene lotes asociados. Puedes SUSPENDERLO en su lugar.";
        $tipo_mensaje = "warning";
    } else {
        $sql = "DELETE FROM medicamentos WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $mensaje = "✅ Medicamento eliminado correctamente.";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al eliminar: " . $stmt->error;
                $tipo_mensaje = "danger";
            }
            $stmt->close();
        } else {
            $mensaje = "Error al preparar la eliminación.";
            $tipo_mensaje = "danger";
        }
    }
}
$categoria_filtro = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;
$laboratorio_filtro = isset($_GET['laboratorio']) ? intval($_GET['laboratorio']) : 0;
$estado_filtro = isset($_GET['estado']) ? intval($_GET['estado']) : 1;
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

$por_pagina = 10;
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$offset = ($pagina - 1) * $por_pagina;

$where = [];
$params = [];
$types = "";

if ($categoria_filtro > 0) {
    $where[] = "m.categoria_id = ?";
    $params[] = $categoria_filtro;
    $types .= "i";
}
if ($laboratorio_filtro > 0) {
    $where[] = "m.laboratorio_id = ?";
    $params[] = $laboratorio_filtro;
    $types .= "i";
}
if ($estado_filtro != 2) {
    $where[] = "m.estado = ?";
    $params[] = $estado_filtro;
    $types .= "i";
}
if (!empty($buscar)) {
    $where[] = "(m.nombre LIKE ? OR m.codigo_minsa LIKE ?)";
    $busqueda = "%$buscar%";
    $params[] = $busqueda;
    $params[] = $busqueda;
    $types .= "ss";
}
$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$total = 0;
$total_paginas = 0;
$medicamentos = null;

if ($tablas_existen) {
    $sql_count = "SELECT COUNT(*) as total FROM medicamentos m $where_clause";
    $stmt_count = $conn->prepare($sql_count);
    if ($stmt_count && !empty($params)) {
        $stmt_count->bind_param($types, ...$params);
    }
    if ($stmt_count) {
        $stmt_count->execute();
        $total = $stmt_count->get_result()->fetch_assoc()['total'];
        $total_paginas = ceil($total / $por_pagina);
        $stmt_count->close();
    }

    $sql = "
        SELECT m.*, c.nombre as categoria_nombre, l.nombre as laboratorio_nombre
        FROM medicamentos m
        LEFT JOIN categorias c ON m.categoria_id = c.id
        LEFT JOIN laboratorios l ON m.laboratorio_id = l.id
        $where_clause
        ORDER BY m.nombre ASC
        LIMIT ? OFFSET ?
    ";
    $params[] = $por_pagina;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $medicamentos = $stmt->get_result();
    }
}

$categorias = $tablas_existen ? $conn->query("SELECT id, nombre FROM categorias WHERE estado=1 ORDER BY nombre") : null;
$laboratorios = $tablas_existen ? $conn->query("SELECT id, nombre FROM laboratorios WHERE estado=1 ORDER BY nombre") : null;
?>

<div class="container-fluid px-4 fade-in">

    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden" style="background: linear-gradient(135deg, #2d6a9f 0%, #1a3a5c 100%);">
                <div class="card-body p-4 text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="display-6 fw-bold mb-1"><i class="fas fa-pills me-2"></i>Gestión de Medicamentos</h2>
                            <p class="opacity-75 mb-0">Administra el catálogo de medicamentos por categorías y laboratorios</p>
                        </div>
                        <?php if ($tablas_existen): ?>
                        <button class="btn btn-light btn-lg rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modalMedicamento">
                            <i class="fas fa-plus me-2"></i>Nuevo Medicamento
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show rounded-4" role="alert">
            <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($tablas_existen): ?>
    <div class="card border-0 shadow-lg rounded-4 glass-card mb-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4">
            <h6 class="fw-bold text-primary mb-0"><i class="fas fa-sliders-h me-2"></i>Filtros</h6>
        </div>
        <div class="card-body p-4 pt-2">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-muted">Estado</label>
                    <select name="estado" class="form-select form-select-sm rounded-pill border-0 bg-light">
                        <option value="1" <?php echo $estado_filtro == 1 ? 'selected' : ''; ?>>Activos</option>
                        <option value="0" <?php echo $estado_filtro == 0 ? 'selected' : ''; ?>>Suspendidos</option>
                        <option value="2" <?php echo $estado_filtro == 2 ? 'selected' : ''; ?>>Todos</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted">Categoría</label>
                    <select name="categoria" class="form-select form-select-sm rounded-pill border-0 bg-light">
                        <option value="0">Todas</option>
                        <?php if ($categorias): while ($cat = $categorias->fetch_assoc()): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $categoria_filtro == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo $cat['nombre']; ?>
                            </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted">Laboratorio</label>
                    <select name="laboratorio" class="form-select form-select-sm rounded-pill border-0 bg-light">
                        <option value="0">Todos</option>
                        <?php if ($laboratorios): while ($lab = $laboratorios->fetch_assoc()): ?>
                            <option value="<?php echo $lab['id']; ?>" <?php echo $laboratorio_filtro == $lab['id'] ? 'selected' : ''; ?>>
                                <?php echo $lab['nombre']; ?>
                            </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted">Buscar</label>
                    <input type="text" name="buscar" class="form-control form-control-sm rounded-pill border-0 bg-light" 
                           placeholder="Nombre o código" value="<?php echo htmlspecialchars($buscar); ?>">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <div class="card border-0 shadow-lg rounded-4 glass-card">
        <div class="card-header bg-transparent border-0 pt-4 px-4">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-primary mb-0">
                    <i class="fas fa-list me-2"></i>Medicamentos
                </h6>
                <?php if ($tablas_existen): ?>
                <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2">
                    <?php echo $total; ?> registros
                </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body p-4 pt-2">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="bg-light rounded-4">
                        <tr>
                            <th>ID</th>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th>Laboratorio</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($tablas_existen && $medicamentos && $medicamentos->num_rows > 0): ?>
                            <?php while ($med = $medicamentos->fetch_assoc()): ?>
                                <tr>
                                    <td><span class="badge bg-secondary-subtle text-secondary rounded-pill">#<?php echo $med['id']; ?></span></td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info rounded-pill px-3 py-2">
                                            <?php echo htmlspecialchars($med['codigo_minsa'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($med['nombre']); ?></td>
                                    <td><?php echo $med['categoria_nombre'] ?? '<span class="text-muted">Sin categoría</span>'; ?></td>
                                    <td><?php echo $med['laboratorio_nombre'] ?? '<span class="text-muted">Sin laboratorio</span>'; ?></td>
                                    <td>
                                        <?php if ($med['estado'] == 1): ?>
                                            <span class="badge bg-success-subtle text-success rounded-pill">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning rounded-pill">Suspendido</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-info rounded-pill px-3" onclick="editarMedicamento(<?php echo $med['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($med['estado'] == 1): ?>
                                            <a href="?cambiar_estado=<?php echo $med['id']; ?>&suspender=1" class="btn btn-sm btn-warning rounded-pill px-3" onclick="return confirm('¿Seguro que deseas SUSPENDER este medicamento?')">
                                                <i class="fas fa-pause-circle"></i> Suspender
                                            </a>
                                        <?php else: ?>
                                            <a href="?cambiar_estado=<?php echo $med['id']; ?>&activar=1" class="btn btn-sm btn-success rounded-pill px-3" onclick="return confirm('¿Deseas REACTIVAR este medicamento?')">
                                                <i class="fas fa-play-circle"></i> Reactivar
                                            </a>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-danger rounded-pill px-3" onclick="eliminarMedicamento(<?php echo $med['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-pills fa-3x mb-3 d-block"></i>
                                    <h5><?php echo $tablas_existen ? 'No hay medicamentos que coincidan con los filtros' : 'Error: Las tablas de categorías y laboratorios no existen.'; ?></h5>
                                    <?php if (!$tablas_existen): ?>
                                    <p class="small">Ejecuta el script SQL para crear las tablas necesarias.</p>
                                    <?php else: ?>
                                    <p class="small">Haz clic en "Nuevo Medicamento" para agregar uno.</p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($tablas_existen): ?>
<div class="modal fade" id="modalMedicamento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content glass-card rounded-4 border-0">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-primary" id="modalTitle">
                    <i class="fas fa-pills me-2"></i>Nuevo Medicamento
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="formMedicamento">
                <div class="modal-body p-4">
                    <input type="hidden" name="accion" id="accion" value="crear">
                    <input type="hidden" name="id" id="id" value="0">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Nombre *</label>
                            <input type="text" name="nombre" id="nombre" class="form-control rounded-pill border-0 bg-light" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Código MINSA</label>
                            <div class="input-group">
                                <input type="text" name="codigo_minsa" id="codigo_minsa" class="form-control rounded-start-pill border-0 bg-light" 
                                       placeholder="Se genera automáticamente">
                                <button class="btn btn-outline-secondary rounded-end-pill" type="button" 
                                        onclick="generarCodigo()" title="Generar código manualmente">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Se genera automáticamente al guardar. Ejemplo: <strong>PAR-001</strong>
                            </small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Categoría</label>
                            <select name="categoria_id" id="categoria_id" class="form-select rounded-pill border-0 bg-light">
                                <option value="">Sin categoría</option>
                                <?php if ($categorias): $categorias->data_seek(0); while ($cat = $categorias->fetch_assoc()): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo $cat['nombre']; ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Laboratorio</label>
                            <select name="laboratorio_id" id="laboratorio_id" class="form-select rounded-pill border-0 bg-light">
                                <option value="">Sin laboratorio</option>
                                <?php if ($laboratorios): $laboratorios->data_seek(0); while ($lab = $laboratorios->fetch_assoc()): ?>
                                    <option value="<?php echo $lab['id']; ?>"><?php echo $lab['nombre']; ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4 px-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">
                        <i class="fas fa-save me-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<style>
    .glass-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.3); }
    .bg-primary-subtle { background: #d1ecf1 !important; color: #0c5460 !important; }
    .bg-secondary-subtle { background: #e2e3e5 !important; color: #383d41 !important; }
    .bg-success-subtle { background: #d4edda !important; color: #155724 !important; }
    .bg-warning-subtle { background: #fff3cd !important; color: #856404 !important; }
    .bg-info-subtle { background: #d1ecf1 !important; color: #0c5460 !important; }
    .fade-in { animation: fadeUp 0.6s ease forwards; }
    @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .page-item.active .page-link { background: #4e73df; border-color: #4e73df; color: #fff; }
    .page-link { border: none; color: #4a5b7a; background: #f8f9fa; }
    .page-link:hover { background: #e9ecef; color: #224abe; }
</style>

<script>
function editarMedicamento(id) {
    fetch('ajax_medicamento.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('accion').value = 'editar';
            document.getElementById('id').value = data.id;
            document.getElementById('nombre').value = data.nombre;
            document.getElementById('codigo_minsa').value = data.codigo_minsa || '';
            document.getElementById('categoria_id').value = data.categoria_id || '';
            document.getElementById('laboratorio_id').value = data.laboratorio_id || '';
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Editar Medicamento';
            new bootstrap.Modal(document.getElementById('modalMedicamento')).show();
        })
        .catch(() => {
            window.location.href = '?editar=' + id;
        });
}

function eliminarMedicamento(id) {
    Swal.fire({
        title: '¿Eliminar medicamento?',
        text: 'Esta acción no se puede deshacer. Solo se permite si no tiene lotes asociados.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '?eliminar=' + id;
        }
    });
}

function generarCodigo() {
    const nombre = document.getElementById('nombre').value;
    if (!nombre) {
        Swal.fire({
            icon: 'warning',
            title: 'Nombre requerido',
            text: 'Ingresa el nombre del medicamento primero para generar un código.',
        });
        return;
    }
    const prefijo = nombre.substring(0, 3).toUpperCase().replace(/[^A-Z]/g, 'X');
    const codigo = prefijo + '-' + String(Math.floor(Math.random() * 900) + 100);
    document.getElementById('codigo_minsa').value = codigo;
}

document.getElementById('modalMedicamento')?.addEventListener('hidden.bs.modal', function () {
    document.getElementById('formMedicamento').reset();
    document.getElementById('accion').value = 'crear';
    document.getElementById('id').value = '0';
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-pills me-2"></i>Nuevo Medicamento';
    document.getElementById('codigo_minsa').value = '';
});
</script>

<?php require_once '../../includes/footer.php'; ?>