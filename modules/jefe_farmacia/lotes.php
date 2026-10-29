<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($conn) || $conn === null) {
    die('Error: No se pudo establecer la conexión a la base de datos. Verifica el archivo config/database.php');
}

if ($_SESSION['tipo_usuario'] != 'jefe_farmacia') {
    header("Location: ../../login.php");
    exit();
}


function generarCodigoMedicamento($nombre, $conn) {
    $nombre_limpio = preg_replace('/[^a-zA-Z]/', '', $nombre);
    $prefijo = substr($nombre_limpio, 0, 3);
    $prefijo = ucfirst(strtolower($prefijo));
    if (strlen($prefijo) < 3) {
        $prefijo = str_pad($prefijo, 3, 'X');
    }

    $sql_buscar = "SELECT codigo_minsa FROM medicamentos WHERE codigo_minsa LIKE ? ORDER BY id DESC LIMIT 1";
    $stmt = $conn->prepare($sql_buscar);
    $like = $prefijo . '-%';
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $stmt->close();

    $nuevo_numero = 1;
    if ($resultado && $resultado->num_rows > 0) {
        $ultimo_codigo = $resultado->fetch_assoc()['codigo_minsa'];
        $partes = explode('-', $ultimo_codigo);
        if (count($partes) == 2) {
            $nuevo_numero = intval($partes[1]) + 1;
        }
    }

    return $prefijo . '-' . str_pad($nuevo_numero, 4, '0', STR_PAD_LEFT);
}

if (isset($_GET['get_preview_code'])) {
    $nombre_preview = $_GET['nombre'] ?? '';
    if (!empty($nombre_preview)) {
        echo generarCodigoMedicamento($nombre_preview, $conn);
    } else {
        echo "Escribe el nombre...";
    }
    exit;
}


$titulo_pagina = "Gestión de Lotes";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$mensaje = '';
$tipo_mensaje = '';

function generarCodigoQR($medicamento_id, $lote_id) {
    return 'QR-' . $medicamento_id . '-' . $lote_id . '-' . date('YmdHis');
}

if (isset($_POST['crear_medicamento_lote'])) {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $categoria_id = intval($_POST['categoria_id']);
    $fecha_vencimiento = $_POST['fecha_vencimiento'];
    
    $cajas = intval($_POST['cajas'] ?? 0);
    $contenido_caja = intval($_POST['contenido_caja'] ?? 1);
    $pastillas_blister = intval($_POST['pastillas_blister'] ?? 1);
    $unidades_sueltas = intval($_POST['unidades_sueltas'] ?? 0);

    $tipo = $_POST['tipo_presentacion'] ?? 'pastillas';


    if ($tipo === 'pastillas' || $tipo === 'frasco') {
        $cantidad_total_unidades = ($cajas * $contenido_caja * $pastillas_blister) + $unidades_sueltas;
    } else {
        $cantidad_total_unidades = ($cajas * $contenido_caja) + $unidades_sueltas;
    }

    if (empty($nombre) || $categoria_id <= 0 || empty($fecha_vencimiento) || $cantidad_total_unidades <= 0) {
        $mensaje = "Por favor, complete todos los campos obligatorios y asegúrese de que el total de unidades sea mayor a 0.";
        $tipo_mensaje = "danger";
    } else {
        $codigo_minsa_generado = generarCodigoMedicamento($nombre, $conn);
        
        $conn->begin_transaction();
        try {
            $sql_med = "INSERT INTO medicamentos (nombre, descripcion, categoria_id, codigo_minsa, estado) VALUES (?, ?, ?, ?, 1)";
            $stmt_med = $conn->prepare($sql_med);
            $stmt_med->bind_param("ssis", $nombre, $descripcion, $categoria_id, $codigo_minsa_generado);
            $stmt_med->execute();
            $nuevo_medicamento_id = $stmt_med->insert_id;
            $stmt_med->close();

            $codigo_qr = generarCodigoQR($nuevo_medicamento_id, 'NEW');
            $sql_lote = "INSERT INTO lotes (medicamento_id, codigo_qr, cantidad, fecha_vencimiento) VALUES (?, ?, ?, ?)";
            $stmt_lote = $conn->prepare($sql_lote);
            $stmt_lote->bind_param("isis", $nuevo_medicamento_id, $codigo_qr, $cantidad_total_unidades, $fecha_vencimiento);
            $stmt_lote->execute();
            $nuevo_lote_id = $stmt_lote->insert_id;
            $stmt_lote->close();

            $codigo_qr_real = generarCodigoQR($nuevo_medicamento_id, $nuevo_lote_id);
            $conn->query("UPDATE lotes SET codigo_qr = '$codigo_qr_real' WHERE id = $nuevo_lote_id");

            $conn->commit();
            $mensaje = "¡Medicamento creado con el código <b>$codigo_minsa_generado</b>! Se ingresaron <b>$cantidad_total_unidades unidades</b> al inventario.";
            $tipo_mensaje = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $mensaje = "Error al crear el medicamento: " . $e->getMessage();
            $tipo_mensaje = "danger";
        }
    }
}


if (isset($_POST['agregar_lote'])) {
    $medicamento_id = intval($_POST['medicamento_id']);
    $cantidad = intval($_POST['cantidad']);
    $fecha_vencimiento = $_POST['fecha_vencimiento'];

    if ($medicamento_id <= 0 || $cantidad <= 0 || empty($fecha_vencimiento)) {
        $mensaje = "Todos los campos son obligatorios y deben ser válidos.";
        $tipo_mensaje = "danger";
    } else {
        $codigo_qr = generarCodigoQR($medicamento_id, 'NEW');
        $sql = "INSERT INTO lotes (medicamento_id, codigo_qr, cantidad, fecha_vencimiento) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $mensaje = "Error al preparar la consulta: " . $conn->error;
            $tipo_mensaje = "danger";
        } else {
            $stmt->bind_param("isis", $medicamento_id, $codigo_qr, $cantidad, $fecha_vencimiento);
            if ($stmt->execute()) {
                $nuevo_id = $stmt->insert_id;
                $codigo_qr_real = generarCodigoQR($medicamento_id, $nuevo_id);
                $conn->query("UPDATE lotes SET codigo_qr = '$codigo_qr_real' WHERE id = $nuevo_id");
                $mensaje = "Lote agregado correctamente. Código QR: $codigo_qr_real";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al agregar el lote: " . $stmt->error;
                $tipo_mensaje = "danger";
            }
            $stmt->close();
        }
    }
}

if (isset($_POST['actualizar_cantidad'])) {
    $lote_id = intval($_POST['lote_id']);
    $nueva_cantidad = intval($_POST['cantidad']);
    if ($nueva_cantidad < 0) {
        $mensaje = "La cantidad no puede ser negativa.";
        $tipo_mensaje = "danger";
    } else {
        $sql = "UPDATE lotes SET cantidad = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ii", $nueva_cantidad, $lote_id);
            if ($stmt->execute()) {
                $mensaje = "Cantidad actualizada correctamente.";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al actualizar: " . $stmt->error;
                $tipo_mensaje = "danger";
            }
            $stmt->close();
        }
    }
}

if (isset($_GET['eliminar_lote']) && is_numeric($_GET['eliminar_lote'])) {
    $lote_id = intval($_GET['eliminar_lote']);
    $check = $conn->query("SELECT COUNT(*) as total FROM receta_detalles WHERE lote_id = $lote_id");
    if ($check) {
        $usado = $check->fetch_assoc()['total'];
        if ($usado > 0) {
            $mensaje = "No se puede eliminar el lote porque está asociado a una receta.";
            $tipo_mensaje = "danger";
        } else {
            $conn->query("DELETE FROM lotes WHERE id = $lote_id");
            $mensaje = "Lote eliminado correctamente.";
            $tipo_mensaje = "success";
        }
    }
}


$medicamento_filtro = isset($_GET['medicamento']) ? intval($_GET['medicamento']) : 0;
$estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : 'todos';
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

$where = [];
$params = [];
$types = "";

if ($medicamento_filtro > 0) {
    $where[] = "l.medicamento_id = ?";
    $params[] = $medicamento_filtro;
    $types .= "i";
}
if ($estado_filtro != 'todos') {
    if ($estado_filtro == 'vigente') {
        $where[] = "l.fecha_vencimiento > DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
    } elseif ($estado_filtro == 'proximo') {
        $where[] = "l.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
    } elseif ($estado_filtro == 'vencido') {
        $where[] = "l.fecha_vencimiento < CURDATE()";
    }
}
if (!empty($buscar)) {
    $where[] = "(m.nombre LIKE ? OR l.codigo_qr LIKE ? OR m.codigo_minsa LIKE ?)";
    $busqueda = "%$buscar%";
    $params[] = $busqueda;
    $params[] = $busqueda;
    $params[] = $busqueda;
    $types .= "sss";
}
$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$por_pagina = 10;
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$offset = ($pagina - 1) * $por_pagina;

$total = 0;
$total_paginas = 0;

$sql_count = "SELECT COUNT(*) as total FROM lotes l JOIN medicamentos m ON l.medicamento_id = m.id $where_clause";
$stmt_count = $conn->prepare($sql_count);
if ($stmt_count) {
    if (!empty($params)) $stmt_count->bind_param($types, ...$params);
    $stmt_count->execute();
    $result = $stmt_count->get_result();
    if ($result) {
        $total = $result->fetch_assoc()['total'];
        $total_paginas = ceil($total / $por_pagina);
    }
    $stmt_count->close();
}

$lotes = null;
$sql = "SELECT l.*, m.nombre as medicamento_nombre, m.codigo_minsa 
        FROM lotes l 
        JOIN medicamentos m ON l.medicamento_id = m.id 
        $where_clause 
        ORDER BY l.fecha_vencimiento ASC
        LIMIT ? OFFSET ?";
$params[] = $por_pagina;
$params[] = $offset;
$types .= "ii";
$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $lotes = $stmt->get_result();
    $stmt->close();
}

$sql_medicamentos_all = "SELECT m.id, m.nombre, m.codigo_minsa, c.nombre AS categoria_nombre
                         FROM medicamentos m
                         LEFT JOIN categorias c ON m.categoria_id = c.id
                         WHERE m.estado = 1 
                         ORDER BY m.nombre ASC";
$medicamentos_all = $conn->query($sql_medicamentos_all);

$categorias = $conn->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC");
?>

<div class="container-fluid px-4 fade-in">
    <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show rounded-4" role="alert">
            <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden" style="background: linear-gradient(135deg, #2d6a9f 0%, #1a3a5c 100%);">
                <div class="card-body p-4 text-white">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div>
                            <h2 class="display-6 fw-bold mb-1"><i class="fas fa-boxes me-2"></i>Gestión de Lotes</h2>
                            <p class="opacity-75 mb-0">Administra el inventario detallado por lotes y unidades</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="inventario.php" class="btn btn-light btn-lg rounded-pill px-4">
                                <i class="fas fa-arrow-left me-2"></i> Dashboard
                            </a>
                            <button class="btn btn-success btn-lg rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modalCrearMedicamento">
                                <i class="fas fa-capsules me-2"></i> Nuevo Medicamento
                            </button>
                            <button class="btn btn-primary btn-lg rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modalAgregarLote">
                                <i class="fas fa-plus-circle me-2"></i> Lote Extra
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-lg rounded-4 glass-card mb-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4">
            <h6 class="fw-bold text-primary mb-0"><i class="fas fa-sliders-h me-2"></i>Filtros</h6>
        </div>
        <div class="card-body p-4 pt-2">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted">Medicamento</label>
                    <select name="medicamento" class="form-select form-select-sm rounded-pill border-0 bg-light">
                        <option value="0">Todos</option>
                        <?php 
                        if ($medicamentos_all && $medicamentos_all->num_rows > 0) {
                            $medicamentos_all->data_seek(0);
                            while ($med = $medicamentos_all->fetch_assoc()): ?>
                                <option value="<?php echo $med['id']; ?>" <?php echo $medicamento_filtro == $med['id'] ? 'selected' : ''; ?>>
                                    <?php echo $med['nombre']; ?> (<?php echo $med['codigo_minsa']; ?>)
                                </option>
                            <?php endwhile; 
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted">Estado</label>
                    <select name="estado" class="form-select form-select-sm rounded-pill border-0 bg-light">
                        <option value="todos" <?php echo $estado_filtro == 'todos' ? 'selected' : ''; ?>>Todos</option>
                        <option value="vigente" <?php echo $estado_filtro == 'vigente' ? 'selected' : ''; ?>>Vigente</option>
                        <option value="proximo" <?php echo $estado_filtro == 'proximo' ? 'selected' : ''; ?>>Próximo a vencer</option>
                        <option value="vencido" <?php echo $estado_filtro == 'vencido' ? 'selected' : ''; ?>>Vencido</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-muted">Buscar</label>
                    <input type="text" name="buscar" class="form-control form-control-sm rounded-pill border-0 bg-light" 
                           placeholder="Nombre, código MINSA o QR" value="<?php echo htmlspecialchars($buscar); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 w-100">
                        <i class="fas fa-search me-1"></i> Buscar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card border-0 shadow-lg rounded-4 glass-card">
        <div class="card-header bg-transparent border-0 pt-4 px-4">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-primary mb-0"><i class="fas fa-list me-2"></i>Lotes en Inventario</h6>
                <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2"><?php echo $total; ?> registros</span>
            </div>
        </div>
        <div class="card-body p-4 pt-2">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="bg-light rounded-4">
                        <tr>
                            <th>ID</th>
                            <th>Código MINSA</th>
                            <th>Medicamento</th>
                            <th>Código QR</th>
                            <th class="text-center">Unidades Totales</th>
                            <th>Vencimiento</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($lotes && $lotes->num_rows > 0): ?>
                            <?php while ($lote = $lotes->fetch_assoc()): 
                                $hoy = new DateTime();
                                $vencimiento = new DateTime($lote['fecha_vencimiento']);
                                $dias = $hoy->diff($vencimiento)->days;
                                if ($vencimiento < $hoy) {
                                    $badge = 'danger';
                                    $estado_texto = 'Vencido';
                                } elseif ($dias <= 30) {
                                    $badge = 'warning';
                                    $estado_texto = 'Próximo a vencer';
                                } else {
                                    $badge = 'success';
                                    $estado_texto = 'Vigente';
                                }
                            ?>
                                <tr>
                                    <td><span class="badge bg-secondary-subtle text-secondary rounded-pill">#<?php echo $lote['id']; ?></span></td>
                                    <td><strong class="text-primary"><?php echo htmlspecialchars($lote['codigo_minsa']); ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($lote['medicamento_nombre']); ?></strong></td>
                                    <td>
                                        <code><?php echo $lote['codigo_qr']; ?></code>
                                        <button class="btn btn-sm btn-link" onclick="copiarQR('<?php echo $lote['codigo_qr']; ?>')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary fs-6 rounded-pill px-3"><?php echo $lote['cantidad']; ?> uds</span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($lote['fecha_vencimiento'])); ?></td>
                                    <td><span class="badge bg-<?php echo $badge; ?>-subtle text-<?php echo $badge; ?>-emphasis rounded-pill px-3 py-2"><?php echo $estado_texto; ?></span></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-warning rounded-pill px-3" onclick="editarCantidad(<?php echo $lote['id']; ?>, <?php echo $lote['cantidad']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger rounded-pill px-3" onclick="eliminarLote(<?php echo $lote['id']; ?>, '<?php echo addslashes($lote['medicamento_nombre']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">No hay lotes que coincidan con los filtros.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_paginas > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link rounded-pill mx-1" href="?pagina=<?php echo $pagina-1; ?>&medicamento=<?php echo $medicamento_filtro; ?>&estado=<?php echo $estado_filtro; ?>&buscar=<?php echo urlencode($buscar); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <li class="page-item <?php echo $pagina == $i ? 'active' : ''; ?>">
                                <a class="page-link rounded-pill mx-1" href="?pagina=<?php echo $i; ?>&medicamento=<?php echo $medicamento_filtro; ?>&estado=<?php echo $estado_filtro; ?>&buscar=<?php echo urlencode($buscar); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $pagina >= $total_paginas ? 'disabled' : ''; ?>">
                            <a class="page-link rounded-pill mx-1" href="?pagina=<?php echo $pagina+1; ?>&medicamento=<?php echo $medicamento_filtro; ?>&estado=<?php echo $estado_filtro; ?>&buscar=<?php echo urlencode($buscar); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCrearMedicamento" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content glass-card rounded-4 border-0">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-success"><i class="fas fa-capsules me-2"></i>Nuevo Medicamento y Lote Inicial</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Nombre del Medicamento *</label>
                            <input type="text" name="nombre" id="nombre_medicamento" class="form-control rounded-pill border-0 bg-light" required placeholder="Ej: Amoxicilina 500mg">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Categoría *</label>
                            <select name="categoria_id" class="form-select rounded-pill border-0 bg-light" required>
                                <option value="">Seleccione...</option>
                                <?php 
                                if ($categorias && $categorias->num_rows > 0) {
                                    $categorias->data_seek(0);
                                    while ($cat = $categorias->fetch_assoc()): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo $cat['nombre']; ?></option>
                                    <?php endwhile; 
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Código Asignado (Automático)</label>
                            <input type="text" id="preview_codigo" class="form-control rounded-pill border-0 bg-light fw-bold text-success" value="Escribe el nombre..." readonly style="border-left: 4px solid #198754 !important;">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Fecha de Vencimiento *</label>
                            <input type="date" name="fecha_vencimiento" class="form-control rounded-pill border-0 bg-light" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold small">Descripción</label>
                            <textarea name="descripcion" class="form-control rounded-4 border-0 bg-light" rows="2" placeholder="Opcional"></textarea>
                        </div>
                    </div>

   
                    <div class="p-3 rounded-4 bg-light border border-success border-opacity-25">
  
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <h6 class="fw-bold text-success mb-0"><i class="fas fa-calculator me-2"></i>Ingreso de Lote Inicial</h6>
                            <div style="width: 320px;">
                                <select id="tipo_presentacion" name="tipo_presentacion" class="form-select form-select-sm border-0 bg-white shadow-sm fw-bold text-dark" onchange="cambiarPresentacion()">
                                    <option value="pastillas">💊 Pastillas / Cápsulas</option>
                                    <option value="jarabe">🍯 Jarabes (Frascos)</option>
                                    <option value="gotero">💧 Goteros (Unidades)</option>
                                    <option value="frasco">🧴 Frasco de Pastillas</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row g-3 align-items-end text-center">
                        
                            <div class="col-md-3">
                                <label class="form-label text-dark fw-bold small mb-1" id="lbl_nivel1">Cajas</label>
                                <input type="number" name="cajas" id="calc_cajas" class="form-control text-center fw-bold" value="1" min="1" oninput="calcularUnidades()">
                            </div>
                            <div class="col-md-1 pb-2 text-muted"><i class="fas fa-times"></i></div>
                            
                            <div class="col-md-3">
                                <label class="form-label text-dark fw-bold small mb-1" id="lbl_nivel2">Frascos por Caja</label>
                                <input type="number" name="contenido_caja" id="calc_contenido" class="form-control text-center fw-bold" value="1" min="1" oninput="calcularUnidades()">
                            </div>
                            <div class="col-md-1 pb-2 text-muted" id="simbolo_x2"><i class="fas fa-times"></i></div>

                            <div class="col-md-2">
                                <label class="form-label text-dark fw-bold small mb-1" id="lbl_nivel3">Pastillas</label>
                                <input type="number" name="pastillas_blister" id="calc_pastillas" class="form-control text-center fw-bold" value="1" min="1" oninput="calcularUnidades()">
                            </div>
                            <div class="col-md-1 pb-2 text-muted"><i class="fas fa-plus"></i></div>
                            
                            <div class="col-md-1">
                                <label class="form-label text-dark fw-bold small mb-1" id="lbl_sueltas">Sueltos</label>
                                <input type="number" name="unidades_sueltas" id="calc_sueltas" class="form-control text-center fw-bold" value="0" min="0" oninput="calcularUnidades()">
                            </div>
                        </div>

                        <hr class="my-3">
                        <div class="d-flex justify-content-between align-items-center bg-white p-3 rounded-3 border">
                            <h6 class="mb-0 fw-bold text-dark">Total a ingresar:</h6>
                            <h4 class="mb-0 fw-bold text-success" id="total_pastillas_texto">1 Frasco</h4>
                        </div>
                    </div>

                </div>
                <div class="modal-footer border-0 pb-4 px-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="crear_medicamento_lote" class="btn btn-success rounded-pill px-4">
                        <i class="fas fa-save me-1"></i> Crear Medicamento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAgregarLote" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content glass-card rounded-4 border-0">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-primary"><i class="fas fa-plus-circle me-2"></i>Agregar Lote Extra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Medicamento *</label>
                        <select name="medicamento_id" class="form-select rounded-pill border-0 bg-light" required>
                            <option value="">Seleccione</option>
                            <?php
                            if ($medicamentos_all && $medicamentos_all->num_rows > 0) {
                                $medicamentos_all->data_seek(0);
                                while ($m = $medicamentos_all->fetch_assoc()): ?>
                                    <option value="<?php echo $m['id']; ?>"><?php echo $m['nombre']; ?> (<?php echo $m['codigo_minsa']; ?>)</option>
                                <?php endwhile; 
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Cantidad Total (Unidades) *</label>
                        <input type="number" name="cantidad" class="form-control rounded-pill border-0 bg-light" required min="1" placeholder="Ej: 500">
                        <small class="text-muted">Ingrese el total individual (pastillas, frascos, goteros).</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Fecha de Vencimiento *</label>
                        <input type="date" name="fecha_vencimiento" class="form-control rounded-pill border-0 bg-light" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4 px-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="agregar_lote" class="btn btn-primary rounded-pill px-4">
                        <i class="fas fa-save me-1"></i> Guardar Lote
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarCantidad" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content glass-card rounded-4 border-0">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-warning"><i class="fas fa-edit me-2"></i>Actualizar Cantidad</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="lote_id" id="lote_id_editar">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nueva Cantidad (Unidades Totales)</label>
                        <input type="number" name="cantidad" class="form-control rounded-pill border-0 bg-light" id="cantidad_editar" required min="0">
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4 px-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="actualizar_cantidad" class="btn btn-primary rounded-pill px-4">
                        <i class="fas fa-save me-1"></i> Actualizar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .glass-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.3); }
    .bg-primary-subtle { background: #d1ecf1 !important; color: #0c5460 !important; }
    .bg-success-subtle { background: #d4edda !important; color: #155724 !important; }
    .bg-warning-subtle { background: #fff3cd !important; color: #856404 !important; }
    .bg-danger-subtle { background: #f8d7da !important; color: #721c24 !important; }
    .bg-secondary-subtle { background: #e2e3e5 !important; color: #383d41 !important; }
    .fade-in { animation: fadeUp 0.6s ease forwards; }
    @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .page-item.active .page-link { background: #4e73df; border-color: #4e73df; color: #fff; }
    .page-link { border: none; color: #4a5b7a; background: #f8f9fa; }
    .page-link:hover { background: #e9ecef; color: #224abe; }
</style>

<script>

document.addEventListener('DOMContentLoaded', function() {
    const nombreInput = document.getElementById('nombre_medicamento');
    const previewInput = document.getElementById('preview_codigo');

    if (nombreInput && previewInput) {
        nombreInput.addEventListener('input', function() {
            let nombre = this.value;

            if (nombre.trim().length > 0) {
                previewInput.value = "Calculando...";
                previewInput.classList.add('text-muted');
                previewInput.classList.remove('text-success');

                fetch('?get_preview_code=1&nombre=' + encodeURIComponent(nombre), { cache: "no-store" })
                    .then(response => {
                        if (!response.ok) throw new Error('Error en el servidor');
                        return response.text();
                    })
                    .then(data => {
                        if (data.includes('<html') || data.includes('<!DOCTYPE')) {
                            previewInput.value = "Error de sesión";
                            previewInput.classList.add('text-danger');
                        } else {
                            previewInput.value = data;
                            previewInput.classList.remove('text-muted', 'text-danger');
                            previewInput.classList.add('text-success');
                        }
                    })
                    .catch(error => {
                        console.error(error);
                        previewInput.value = "Error al generar";
                        previewInput.classList.add('text-danger');
                    });
            } else {
                previewInput.value = "Escribe el nombre...";
                previewInput.classList.remove('text-success', 'text-danger');
                previewInput.classList.add('text-muted');
            }
        });
    }
});

function cambiarPresentacion() {
    let tipo = document.getElementById('tipo_presentacion').value;
    let lbl1 = document.getElementById('lbl_nivel1');
    let lbl2 = document.getElementById('lbl_nivel2');
    let lbl3 = document.getElementById('lbl_nivel3');
    let lbl4 = document.getElementById('lbl_sueltas');
    
    let val2 = document.getElementById('calc_contenido');
    let val3 = document.getElementById('calc_pastillas');
    let col3 = val3.closest('.col-md-2');
    let simboloX2 = document.getElementById('simbolo_x2');

    if (col3) col3.style.display = 'block';
    if (simboloX2) simboloX2.style.display = 'block';

    if (tipo === 'pastillas') {
        lbl1.innerText = 'Cajas';
        lbl2.innerText = 'Blísters x Caja';
        lbl3.innerText = 'Pastillas x Blíster';
        lbl4.innerText = 'Pastillas Sueltas';
        val2.value = 10;
        val3.value = 10;
    } else if (tipo === 'jarabe') {
        lbl1.innerText = 'Cajas';
        lbl2.innerText = 'Frascos por Caja';
        lbl3.innerText = 'Multiplicador';
        lbl4.innerText = 'Frascos Sueltos';
        val2.value = 1; 
        val3.value = 1; 
        if (col3) col3.style.display = 'none';
        if (simboloX2) simboloX2.style.display = 'none';
    } else if (tipo === 'gotero') {
        lbl1.innerText = 'Cajas';
        lbl2.innerText = 'Goteros por Caja';
        lbl3.innerText = 'Multiplicador';
        lbl4.innerText = 'Goteros Sueltos';
        val2.value = 1; 
        val3.value = 1; 
        if (col3) col3.style.display = 'none';
        if (simboloX2) simboloX2.style.display = 'none';
    } else if (tipo === 'frasco') {
        lbl1.innerText = 'Cajas';
        lbl2.innerText = 'Frascos por Caja';
        lbl3.innerText = 'Pastillas por Frasco';
        lbl4.innerText = 'Pastillas Sueltas';
        val2.value = 1; 
        val3.value = 50; 
    }

    calcularUnidades();
}

function calcularUnidades() {
    let tipo = document.getElementById('tipo_presentacion').value;
    let cajas = parseInt(document.getElementById('calc_cajas').value) || 0;
    let contenido = parseInt(document.getElementById('calc_contenido').value) || 0;
    let pastillas = parseInt(document.getElementById('calc_pastillas').value) || 1;
    let sueltas = parseInt(document.getElementById('calc_sueltas').value) || 0;

    let total = 0;
    let unidad = 'Unidades';

    if (tipo === 'pastillas') {
        if (contenido === 0 || pastillas === 0) {
            document.getElementById('total_pastillas_texto').innerText = "0 (Revisa los campos)";
            return;
        }
        total = (cajas * contenido * pastillas) + sueltas;
        unidad = 'Pastillas';
    } else if (tipo === 'jarabe') {
        if (contenido === 0) {
            document.getElementById('total_pastillas_texto').innerText = "0 (Revisa los campos)";
            return;
        }
        total = (cajas * contenido * 1) + sueltas;
        unidad = 'Frascos';
    } else if (tipo === 'gotero') {
        if (contenido === 0) {
            document.getElementById('total_pastillas_texto').innerText = "0 (Revisa los campos)";
            return;
        }
        total = (cajas * contenido * 1) + sueltas;
        unidad = 'Goteros';
    } else if (tipo === 'frasco') {
        if (contenido === 0 || pastillas === 0) {
            document.getElementById('total_pastillas_texto').innerText = "0 (Revisa los campos)";
            return;
        }
        total = (cajas * contenido * pastillas) + sueltas;
        unidad = 'Pastillas';
    }

    document.getElementById('total_pastillas_texto').innerText = total.toLocaleString() + ' ' + unidad;
}

function editarCantidad(id, cantidad) {
    document.getElementById('lote_id_editar').value = id;
    document.getElementById('cantidad_editar').value = cantidad;
    new bootstrap.Modal(document.getElementById('modalEditarCantidad')).show();
}

function eliminarLote(id, nombre) {
    Swal.fire({
        title: '¿Eliminar lote?',
        html: `Estás a punto de eliminar el lote de <strong>${nombre}</strong>. Solo se puede eliminar si no está asociado a ninguna receta.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '?eliminar_lote=' + id;
        }
    });
}

function copiarQR(codigo) {
    navigator.clipboard.writeText(codigo).then(() => {
        Swal.fire({ title: '¡Copiado!', text: 'Código QR copiado al portapapeles', icon: 'success', timer: 1500, showConfirmButton: false });
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>