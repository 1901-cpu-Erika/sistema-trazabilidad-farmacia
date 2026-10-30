<?php

require_once '../../config/database.php';

$titulo_pagina = "Crear Receta Digital";

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
date_default_timezone_set('America/Managua');

$fecha_actual = date('d/m/Y h:i:s A');

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] != 'doctor') {
    header("Location: /login.php");
    exit();
}

$doctor_id = $_SESSION['usuario_id'];
$doctor_nombre = $_SESSION['usuario_nombre'] ?? 'Doctor';

$mensaje = '';
$tipo_mensaje = '';

$paciente_seleccionado = null;
$error_paciente = false;

if (isset($_GET['paciente_id']) && !empty($_GET['paciente_id'])) {
    $id_p = intval($_GET['paciente_id']);
    $stmt_p = $conn->prepare("SELECT id, nombre, apellido FROM pacientes WHERE id = ?");
    $stmt_p->bind_param("i", $id_p);
    $stmt_p->execute();
    $res_p = $stmt_p->get_result();

    if ($res_p && $res_p->num_rows > 0) {
        $paciente_seleccionado = $res_p->fetch_assoc();
    } else {
        $error_paciente = true;
        $mensaje = "El paciente seleccionado no existe en el sistema. Por favor, regístrelo primero.";
        $tipo_mensaje = "danger";
    }
    $stmt_p->close();
} else {
    $error_paciente = true;
    $mensaje = "Para crear una receta, primero debe seleccionar un paciente.";
    $tipo_mensaje = "danger";
}

$stmt_doc = $conn->prepare("SELECT codigo_minsa FROM usuarios WHERE id = ?");
$stmt_doc->bind_param("i", $doctor_id);
$stmt_doc->execute();
$res_doc = $stmt_doc->get_result()->fetch_assoc();
$codigo_medico = $res_doc['codigo_minsa'] ?? 'S/C';
$stmt_doc->close();

$sql_meds = "
    SELECT 
        m.id, m.nombre, m.descripcion, m.categoria_id,
        COALESCE(SUM(CASE WHEN l.fecha_vencimiento > CURDATE() THEN l.cantidad ELSE 0 END), 0) AS stock,
        COALESCE(c.nombre, 'Sin categoría') AS categoria_nombre
    FROM medicamentos m
    LEFT JOIN lotes l ON m.id = l.medicamento_id
    LEFT JOIN categorias c ON m.categoria_id = c.id
    GROUP BY m.id, m.nombre, m.descripcion, m.categoria_id, c.nombre
    ORDER BY CASE WHEN c.nombre IS NULL THEN 1 ELSE 0 END, c.nombre ASC, m.nombre ASC
";

$medicamentos = $conn->query($sql_meds);
$medicamentos_agrupados = [];

if ($medicamentos) {
    while ($row = $medicamentos->fetch_assoc()) {
        $categoria = !empty($row['categoria_nombre']) ? $row['categoria_nombre'] : 'Sin categoría';
        if (!isset($medicamentos_agrupados[$categoria])) {
            $medicamentos_agrupados[$categoria] = [];
        }
        $medicamentos_agrupados[$categoria][] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_receta']) && !$error_paciente) {
    $p_id = intval($_POST['paciente_id']);
    $meds_post = $_POST['medicamentos'] ?? [];
    $observaciones = trim($_POST['observaciones'] ?? '');

    if (empty($meds_post)) {
        $mensaje = "Debe añadir al menos un medicamento.";
        $tipo_mensaje = "danger";
    } else {
        $stmt_check = $conn->prepare("SELECT COUNT(*) AS total FROM pacientes WHERE id = ?");
        $stmt_check->bind_param("i", $p_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        if ($result_check['total'] == 0) {
            $mensaje = "❌ El paciente no existe. Por favor, regístrelo primero.";
            $tipo_mensaje = "danger";
        } else {
            $conn->begin_transaction();
            try {
                $fecha_actual = date('d/m/Y H:i:s');
                $firma = "Firmado digitalmente por: " . $doctor_nombre . " | Cód. MINSA: " . $codigo_medico . " | Fecha: " . $fecha_actual;
                $token = bin2hex(random_bytes(32));

                $check_column = $conn->query("SHOW COLUMNS FROM recetas LIKE 'observaciones'");
                $tiene_observaciones = ($check_column && $check_column->num_rows > 0);

                if ($tiene_observaciones) {
                    $sql_r = "INSERT INTO recetas (doctor_id, paciente_id, fecha_receta, estado, firma_electronica, token_acceso, observaciones) VALUES (?, ?, CURDATE(), 'pendiente', ?, ?, ?)";
                    $stmt_r = $conn->prepare($sql_r);
                    $stmt_r->bind_param("iisss", $doctor_id, $p_id, $firma, $token, $observaciones);
                } else {
                    $sql_r = "INSERT INTO recetas (doctor_id, paciente_id, fecha_receta, estado, firma_electronica, token_acceso) VALUES (?, ?, CURDATE(), 'pendiente', ?, ?)";
                    $stmt_r = $conn->prepare($sql_r);
                    $stmt_r->bind_param("iiss", $doctor_id, $p_id, $firma, $token);
                }

                if (!$stmt_r) throw new Exception("Error al preparar la consulta: " . $conn->error);
                $stmt_r->execute();
                $id_receta = $conn->insert_id;

                foreach ($meds_post as $m) {
                    $m_id = intval($m['medicamento_id'] ?? 0);
                    $dos = trim($m['dosis'] ?? '');
                    $cant = intval($m['cantidad'] ?? 0);
                    if ($m_id <= 0 || empty($dos) || $cant <= 0) continue;

                    $sql_d = "INSERT INTO receta_detalles (receta_id, medicamento_id, dosis, cantidad) VALUES (?, ?, ?, ?)";
                    $stmt_d = $conn->prepare($sql_d);
                    $stmt_d->bind_param("iisi", $id_receta, $m_id, $dos, $cant);
                    $stmt_d->execute();
                    $stmt_d->close();
                }

                $stmt_r->close();
                $conn->commit();

                $stmt_info = $conn->prepare("SELECT telefono, tiene_whatsapp, nombre_tutor, telefono_tutor FROM pacientes WHERE id = ?");
                $stmt_info->bind_param("i", $p_id);
                $stmt_info->execute();
                $info_paciente = $stmt_info->get_result()->fetch_assoc();
                $stmt_info->close();

                $numero_envio = "";
                $nombre_envio = "";

                if ($info_paciente['tiene_whatsapp'] == 1 && !empty($info_paciente['telefono'])) {
                    $numero_envio = $info_paciente['telefono'];
                    $nombre_envio = $paciente_seleccionado['nombre'];
                } elseif (!empty($info_paciente['telefono_tutor'])) {
                    $numero_envio = $info_paciente['telefono_tutor'];
                    $nombre_envio = "Tutor: " . $info_paciente['nombre_tutor'];
                }

                $mensaje = "✅ Receta #$id_receta enviada con éxito.";
                $tipo_mensaje = "success";

                $alerta_js = "";
                if (!empty($numero_envio)) {
                    $alerta_js = "alert('PRUEBA DE ENVÍO:\\n\\nSe enviará el mensaje a: $nombre_envio\\nAl número: $numero_envio');";
                } else {
                    $alerta_js = "alert('ATENCIÓN: El paciente no tiene WhatsApp y no se registró un tutor con teléfono.');";
                }

                echo "<script>
                    setTimeout(function() {
                        $alerta_js
                        window.location.href = 'historial_recetas.php';
                    }, 1000);
                </script>";
            } catch (Exception $e) {
                $conn->rollback();
                $mensaje = "❌ Error: " . $e->getMessage();
                $tipo_mensaje = "danger";
            }
        }
    }
}
?>

<div class="receta-container">
    <div class="receta-wrapper">

        <?php if ($mensaje): ?>
            <div class="receta-alert receta-alert-<?php echo htmlspecialchars($tipo_mensaje); ?>">
                <span><?php echo htmlspecialchars($mensaje); ?></span>
                <button type="button" class="receta-close" data-bs-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>

        <?php if ($error_paciente || !$paciente_seleccionado): ?>

            <div class="receta-error-card">
                <div class="receta-error-icon"><i class="fas fa-user-slash"></i></div>
                <h2 class="receta-error-title">Paciente no seleccionado</h2>
                <p class="receta-error-text">
                    Para crear una receta médica, primero debes seleccionar un paciente del sistema
                    o registrar uno nuevo.
                </p>
                <div class="receta-error-buttons">
                    <button type="button" class="receta-btn-error receta-btn-buscar" data-bs-toggle="modal" data-bs-target="#modalBuscarPaciente">
                        <i class="fas fa-search me-2"></i> Buscar Paciente
                    </button>
                    <button type="button" class="receta-btn-error receta-btn-registrar" data-bs-toggle="modal" data-bs-target="#modalRegistrarPaciente">
                        <i class="fas fa-user-plus me-2"></i> Registrar Paciente
                    </button>
                </div>
            </div>

            <div class="modal fade" id="modalBuscarPaciente" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content receta-modal">
                        <div class="modal-header receta-modal-header">
                            <h5 class="modal-title"><i class="fas fa-search me-2"></i> Buscar Paciente</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body receta-modal-body">
                            <div class="receta-modal-search">
                                <i class="fas fa-search"></i>
                                <input type="text" id="buscarPacienteInput" class="receta-modal-search-input" placeholder="Buscar por nombre, apellido, cédula o teléfono...">
                            </div>
                            <div class="receta-modal-table-wrapper">
                                <table class="receta-modal-table" id="tablaPacientesModal">
                                    <thead>
                                        <tr>
                                            <th>Paciente</th>
                                            <th>Edad</th>
                                            <th>Teléfono</th>
                                            <th class="text-end">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyPacientesModal">
                                        <?php
                                        $sql_pac = "SELECT id, nombre, apellido, edad, telefono, cedula FROM pacientes ORDER BY nombre ASC";
                                        $res_pac = $conn->query($sql_pac);

                                        if ($res_pac && $res_pac->num_rows > 0) {
                                            while ($p = $res_pac->fetch_assoc()) {
                                                $nombre_completo = $p['nombre'] . ' ' . $p['apellido'];
                                                $iniciales = strtoupper(substr($p['nombre'], 0, 1) . substr($p['apellido'], 0, 1));
                                                $color = '#' . substr(md5($nombre_completo), 0, 6);
                                                $data_busqueda = strtolower($nombre_completo . ' ' . ($p['cedula'] ?? '') . ' ' . ($p['telefono'] ?? ''));
                                                ?>
                                                <tr data-busqueda="<?php echo htmlspecialchars($data_busqueda); ?>">
                                                    <td>
                                                        <div class="receta-modal-paciente">
                                                            <div class="receta-modal-avatar" style="background: <?php echo $color; ?>;"><?php echo $iniciales; ?></div>
                                                            <div>
                                                                <div class="receta-modal-nombre"><?php echo htmlspecialchars($nombre_completo); ?></div>
                                                                <div class="receta-modal-cedula">
                                                                    <i class="fas fa-id-card me-1"></i>
                                                                    <?php echo !empty($p['cedula']) ? htmlspecialchars($p['cedula']) : 'Sin cédula'; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><span class="receta-modal-edad"><?php echo (int)$p['edad']; ?> años</span></td>
                                                    <td>
                                                        <?php if (!empty($p['telefono'])): ?>
                                                            <span class="receta-modal-tel"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($p['telefono']); ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <a href="crear_receta.php?paciente_id=<?php echo $p['id']; ?>" class="receta-modal-btn-select">
                                                            <i class="fas fa-check me-1"></i> Seleccionar
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php
                                            }
                                        } else {
                                            echo '<tr><td colspan="4" class="receta-modal-empty">No hay pacientes registrados</td></tr>';
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            <div id="sinResultadosModal" class="receta-modal-empty" style="display:none;">
                                <i class="fas fa-search fa-2x mb-2"></i>
                                <p>No se encontraron pacientes con ese criterio</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="modalRegistrarPaciente" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content reg-modal">

                        <div class="modal-header reg-modal-header">
                            <div class="d-flex align-items-center gap-3">
                                <div class="reg-modal-icon"><i class="fas fa-user-plus"></i></div>
                                <div>
                                    <h5 class="reg-modal-title">Registrar Nuevo Paciente</h5>
                                    <p class="reg-modal-sub">Paso <span id="pasoActual">1</span> de 2</p>
                                </div>
                            </div>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="reg-progress">
                            <div class="reg-progress-bar" id="progressBar" style="width: 50%;"></div>
                        </div>

                        <form method="POST" action="registrar_paciente.php" id="formRegistrarPaciente">
                            <div class="modal-body reg-modal-body">

                                <div class="reg-step" id="step1">
                                    <div class="reg-section-title">
                                        <i class="fas fa-id-card"></i> Datos Personales
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="reg-label">Nombre *</label>
                                            <div class="reg-input-group">
                                                <span class="reg-input-icon"><i class="fas fa-user"></i></span>
                                                <input type="text" name="nombre" id="reg_nombre" class="reg-input" placeholder="Ej: Juan" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="reg-label">Apellido *</label>
                                            <div class="reg-input-group">
                                                <span class="reg-input-icon"><i class="fas fa-user"></i></span>
                                                <input type="text" name="apellido" id="reg_apellido" class="reg-input" placeholder="Ej: Pérez" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="reg-label">Cédula</label>
                                            <div class="reg-input-group">
                                                <span class="reg-input-icon"><i class="fas fa-id-card"></i></span>
                                                <input type="text" name="cedula" class="reg-input" placeholder="001-000000-0000X">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="reg-label">Sexo</label>
                                            <div class="reg-input-group">
                                                <span class="reg-input-icon"><i class="fas fa-venus-mars"></i></span>
                                                <select name="sexo" class="reg-input">
                                                    <option value="">Seleccione</option>
                                                    <option value="M">Masculino</option>
                                                    <option value="F">Femenino</option>
                                                    <option value="Otro">Otro</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="reg-label">Fecha de Nacimiento *</label>
                                            <div class="reg-input-group">
                                                <span class="reg-input-icon"><i class="fas fa-calendar-alt"></i></span>
                                                <input type="date" name="fecha_nacimiento" id="fechaNacimientoModal" class="reg-input" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="reg-label">Edad (Años)</label>
                                            <div class="reg-input-group">
                                                <span class="reg-input-icon"><i class="fas fa-birthday-cake"></i></span>
                                                <input type="number" name="edad" id="edadModal" class="reg-input" placeholder="0">
                                            </div>
                                            <small class="reg-help">Si escribe la edad, la fecha se calcula sola</small>
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-12">
                                            <label class="reg-label">Teléfono</label>
                                            <div class="reg-input-group">
                                                <span class="reg-input-icon"><i class="fas fa-phone-alt"></i></span>
                                                <input type="text" name="telefono" class="reg-input" placeholder="">
                                                <div class="reg-check-inline">
                                                    <input class="form-check-input" type="checkbox" name="tiene_whatsapp" value="1" checked id="checkWhatsApp">
                                                    <label><i class="fab fa-whatsapp text-success me-1"></i> WhatsApp</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-3 d-none" id="tutorFields">
                                        <div class="col-md-6">
                                            <label class="reg-label">Nombre del Tutor *</label>
                                            <div class="reg-input-group">
                                                <span class="reg-input-icon"><i class="fas fa-user-friends"></i></span>
                                                <input type="text" name="nombre_tutor" id="reg_nombre_tutor" class="reg-input" placeholder="Ej: María Pérez">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="reg-label">Teléfono del Tutor (WhatsApp) *</label>
                                            <div class="reg-input-group">
                                                <span class="reg-input-icon"><i class="fab fa-whatsapp" style="color: #25D366;"></i></span>
                                                <input type="text" name="telefono_tutor" id="reg_telefono_tutor" class="reg-input" placeholder="Ej: 88888888">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="reg-step d-none" id="step2">
                                    <div class="reg-section-title">
                                        <i class="fas fa-heartbeat" style="color: #ef4444;"></i> Enfermedades Crónicas
                                    </div>

                                    <div class="reg-enf-container">
                                        <div class="reg-toggle-group">
                                            <input type="radio" class="btn-check" name="tiene_enfermedad_cronica_modal" id="cronicaNoModal" value="0" checked>
                                            <label class="reg-toggle-btn" for="cronicaNoModal">
                                                <i class="fas fa-times-circle me-1"></i> No padece
                                            </label>

                                            <input type="radio" class="btn-check" name="tiene_enfermedad_cronica_modal" id="cronicaSiModal" value="1">
                                            <label class="reg-toggle-btn reg-toggle-si" for="cronicaSiModal">
                                                <i class="fas fa-check-circle me-1"></i> Sí padece
                                            </label>
                                        </div>

                                        <div id="contenedorEnfermedadesModal" class="d-none">
                                            <div class="row g-3">
                                                <?php
                                                $lista_enfermedades = [
                                                    'Asma',
                                                    'Cardiopatía',
                                                    'Diabetes Mellitus',
                                                    'Enfermedad Renal Crónica',
                                                    'Hipertensión Arterial'
                                                ];
                                                foreach ($lista_enfermedades as $enfermedad) : ?>
                                                    <div class="col-md-4 col-sm-6">
                                                        <label class="reg-check-card">
                                                            <input class="form-check-input enfermedad-check-modal" type="checkbox"
                                                                   name="enfermedades[]" value="<?php echo htmlspecialchars($enfermedad); ?>">
                                                            <span><?php echo htmlspecialchars($enfermedad); ?></span>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                                <div class="col-md-4 col-sm-6">
                                                    <label class="reg-check-card" for="enf_otra_modal">
                                                        <input class="form-check-input" type="checkbox" name="enfermedades[]" value="otra" id="enf_otra_modal">
                                                        <span>Otra</span>
                                                    </label>
                                                    <input type="text" class="reg-input mt-2" id="otra_enfermedad_modal"
                                                           name="otra_enfermedad" placeholder="Especifique..."
                                                           style="display:none; border: 2px solid #e9ecef; border-radius: 10px;">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <div class="modal-footer reg-modal-footer">
                                <button type="button" class="reg-btn-cancel" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-1"></i> Cancelar
                                </button>
                                <button type="button" class="reg-btn-cancel d-none" id="btnAtras">
                                    <i class="fas fa-arrow-left me-1"></i> Atrás
                                </button>
                                <button type="button" class="reg-btn-save" id="btnSiguiente">
                                    Siguiente <i class="fas fa-arrow-right ms-1"></i>
                                </button>
                                <button type="submit" class="reg-btn-save d-none" id="btnRegistrar">
                                    <i class="fas fa-save me-1"></i> Registrar Paciente
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>

        <?php else: ?>

            <div class="receta-header">
                <div class="receta-header-content">
                    <div class="receta-header-left">
                        <div class="receta-icon-wrapper"><i class="fas fa-prescription"></i></div>
                        <div>
                            <h1 class="receta-title">Nueva Receta Médica</h1>
                            <p class="receta-subtitle">
                                <i class="fas fa-user me-2"></i>
                                Paciente:
                                <strong><?php echo htmlspecialchars($paciente_seleccionado['nombre'] . ' ' . $paciente_seleccionado['apellido']); ?></strong>
                                <span class="receta-divider">|</span>
                                <i class="fas fa-calendar-day me-2"></i>
                                <?php echo date('d/m/Y'); ?>
                            </p>
                        </div>
                    </div>
                    <div class="receta-header-right">
                        <a href="buscar_paciente.php" class="receta-btn receta-btn-light">
                            <i class="fas fa-arrow-left me-2"></i> Volver
                        </a>
                        <span class="receta-badge">Nueva</span>
                    </div>
                </div>
            </div>

            <div class="receta-card">
                <form method="POST" id="formReceta">
                    <input type="hidden" name="paciente_id" value="<?php echo $paciente_seleccionado['id']; ?>">

                    <div class="receta-card-header">
                        <h3 class="receta-card-title"><i class="fas fa-pills me-2"></i> Medicamentos Recetados</h3>
                        <span class="receta-card-badge">Receta #Nueva</span>
                    </div>

                    <div id="contenedor-items">
                        <div class="receta-item">
                            <div class="receta-item-grid">
                                <div class="receta-item-field">
                                    <label class="receta-label">Medicamento *</label>
                                    <select name="medicamentos[0][medicamento_id]" class="receta-select sel-med" required onchange="validarStock(this)">
                                        <option value="">-- Seleccione un medicamento --</option>
                                        <?php if (!empty($medicamentos_agrupados)): ?>
                                            <?php foreach ($medicamentos_agrupados as $categoria => $meds): ?>
                                                <?php if (!empty($meds)): ?>
                                                    <optgroup label="<?php echo htmlspecialchars($categoria); ?>">
                                                        <?php foreach ($meds as $med): ?>
                                                            <option value="<?php echo $med['id']; ?>" data-stock="<?php echo $med['stock']; ?>" <?php echo ($med['stock'] <= 0) ? 'disabled' : ''; ?>>
                                                                <?php echo htmlspecialchars($med['nombre']); ?>
                                                                <?php if ($med['stock'] <= 0): ?>❌ (Sin Stock)<?php else: ?>✅ (<?php echo $med['stock']; ?> disponibles)<?php endif; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </optgroup>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="" disabled>No hay medicamentos disponibles</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="receta-item-field">
                                    <label class="receta-label">Dosis / Indicación *</label>
                                    <input type="text" name="medicamentos[0][dosis]" class="receta-input" placeholder="Ej: 1 tableta c/8h" required>
                                </div>
                                <div class="receta-item-field">
                                    <label class="receta-label">Cantidad *</label>
                                    <input type="number" name="medicamentos[0][cantidad]" class="receta-input inp-cant" min="1" required oninput="validarStock(this)">
                                </div>
                                <div class="receta-item-actions">
                                    <button type="button" class="receta-btn-delete" onclick="eliminarFila(this)">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="receta-btn-add" onclick="agregarItem()">
                        <i class="fas fa-plus-circle me-2"></i> Añadir otro medicamento
                    </button>

                    <hr class="receta-divider-full">

                    <div class="receta-observaciones">
                        <label class="receta-label">📝 Observaciones / Recomendaciones</label>
                        <textarea name="observaciones" class="receta-textarea" rows="4" placeholder="Ej: Tomar después de los alimentos..."></textarea>
                        <small class="receta-help">Instrucciones adicionales para el paciente (opcional).</small>
                    </div>

                    <hr class="receta-divider-full">

                    <div class="receta-firma">
                        <div class="receta-firma-icon"><i class="fas fa-signature"></i></div>
                        <div>
                            <span class="receta-firma-label">Firma Digital:</span>
                            <code class="receta-firma-code">
                                <?php echo htmlspecialchars($doctor_nombre); ?> | MINSA: <?php echo htmlspecialchars($codigo_medico); ?> | <?php echo date('d/m/Y'); ?>
                            </code>
                        </div>
                    </div>

                    <button type="submit" name="crear_receta" class="receta-btn-submit" id="btnGuardar">
                        <i class="fas fa-paper-plane me-2"></i> ENVIAR RECETA A FARMACIA
                    </button>
                </form>
            </div>

        <?php endif; ?>

    </div>
</div>


<style>
.receta-container { background: linear-gradient(135deg, #0f172a 0%, #1a2332 100%); min-height: 100vh; padding: 30px; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
.receta-wrapper { max-width: 900px; margin: 0 auto; }
.receta-alert { padding: 15px 20px; border-radius: 12px; font-weight: 600; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.receta-alert-success { background: linear-gradient(135deg, #065f46, #047857); color: #6ee7b7; border: 1px solid #34d399; }
.receta-alert-danger { background: linear-gradient(135deg, #7f1d1d, #991b1b); color: #fca5a5; border: 1px solid #ef4444; }
.receta-close { background: none; border: none; color: inherit; font-size: 24px; cursor: pointer; opacity: .7; padding: 0; line-height: 1; }

.receta-error-card { background: linear-gradient(135deg, #1e293b, #2d3748); border-radius: 20px; padding: 50px 30px; text-align: center; border: 2px solid #ef4444; box-shadow: 0 20px 50px rgba(0,0,0,.4); }
.receta-error-icon { background: rgba(239,68,68,.15); border-radius: 50%; width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 36px; color: #f87171; }
.receta-error-title { color: #f1f5f9; font-size: 24px; font-weight: 700; margin-bottom: 10px; }
.receta-error-text { color: #94a3b8; font-size: 16px; margin-bottom: 25px; line-height: 1.8; }
.receta-error-buttons { display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; }
.receta-btn-error { color: #fff; padding: 14px 35px; border-radius: 50px; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; transition: all .3s; border: none; cursor: pointer; box-shadow: 0 4px 15px rgba(0,0,0,.3); font-size: 15px; }
.receta-btn-error:hover { transform: translateY(-3px); box-shadow: 0 8px 30px rgba(0,0,0,.4); }
.receta-btn-buscar { background: linear-gradient(135deg, #0B2A4A 0%, #1A6B8A 100%); }
.receta-btn-registrar { background: linear-gradient(135deg, #7B5A00 0%, #C9A84C 100%); }

.receta-modal { background: linear-gradient(135deg, #1e293b, #2d3748); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 20px; overflow: hidden; }
.receta-modal-header { background: linear-gradient(135deg, #1a365d, #2563eb); color: #fff; border: none; padding: 20px 26px; }
.receta-modal-header h5 { font-weight: 700; display: flex; align-items: center; margin: 0; }
.receta-modal-body { padding: 26px; background: #1e293b; }
.receta-modal-search { position: relative; margin-bottom: 20px; }
.receta-modal-search i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #64748b; font-size: 15px; }
.receta-modal-search-input { width: 100%; padding: 12px 16px 12px 44px; border-radius: 12px; border: 2px solid #334155; background: #0f172a; color: #e2e8f0; font-size: 14px; transition: all .3s; }
.receta-modal-search-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); }
.receta-modal-table-wrapper { max-height: 380px; overflow-y: auto; border-radius: 12px; border: 1px solid #334155; }
.receta-modal-table { width: 100%; border-collapse: collapse; color: #e2e8f0; font-size: 14px; }
.receta-modal-table thead { background: #0f172a; position: sticky; top: 0; z-index: 1; }
.receta-modal-table th { padding: 14px 16px; text-align: left; color: #94a3b8; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; border-bottom: 2px solid #334155; }
.receta-modal-table td { padding: 12px 16px; border-bottom: 1px solid #1e293b; }
.receta-modal-table tbody tr:hover { background: rgba(59, 130, 246, 0.05); }
.receta-modal-paciente { display: flex; align-items: center; gap: 12px; }
.receta-modal-avatar { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 13px; flex-shrink: 0; }
.receta-modal-nombre { font-weight: 600; color: #f1f5f9; }
.receta-modal-cedula { font-size: 12px; color: #94a3b8; margin-top: 2px; }
.receta-modal-edad { color: #60a5fa; font-weight: 600; }
.receta-modal-tel { color: #94a3b8; }
.receta-modal-btn-select { background: linear-gradient(135deg, #2563eb, #3b82f6); color: #fff; padding: 8px 18px; border-radius: 50px; text-decoration: none; font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; transition: all .25s; border: none; cursor: pointer; }
.receta-modal-btn-select:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4); color: #fff; }
.receta-modal-empty { text-align: center; padding: 40px 20px; color: #64748b; }

.reg-modal { background: #ffffff; border: none; border-radius: 20px; overflow: hidden; box-shadow: 0 25px 80px rgba(0, 0, 0, 0.35); }
.reg-modal-header { background: linear-gradient(135deg, #1a3a5c 0%, #2d6a9f 55%, #4fa3d1 100%); color: #fff; border: none; padding: 22px 30px; position: relative; overflow: hidden; }
.reg-modal-header::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-image: radial-gradient(circle, rgba(255,255,255,0.12) 1px, transparent 1px); background-size: 20px 20px; opacity: 0.6; }
.reg-modal-header > * { position: relative; z-index: 1; }
.reg-modal-icon { width: 54px; height: 54px; background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: #fff; flex-shrink: 0; box-shadow: 0 8px 24px rgba(0,0,0,0.15); }
.reg-modal-title { font-size: 1.35rem; font-weight: 700; margin: 0 0 2px 0; }
.reg-modal-sub { margin: 0; opacity: 0.9; font-size: 0.85rem; }

.reg-progress { height: 4px; background: #e9ecef; width: 100%; overflow: hidden; }
.reg-progress-bar { height: 100%; background: linear-gradient(90deg, #1a3a5c, #2d6a9f, #4fa3d1); transition: width 0.4s ease; border-radius: 0 4px 4px 0; }

.reg-modal-body { padding: 28px 30px; background: #ffffff; min-height: 380px; }
.reg-step { animation: fadeStep 0.35s ease forwards; }
@keyframes fadeStep { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }

.reg-section-title { color: #1a3a5c; font-size: 1rem; font-weight: 700; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e9ecef; display: flex; align-items: center; gap: 10px; }
.reg-section-title i { color: #2d6a9f; }
.reg-label { font-weight: 600; color: #1a3a5c; font-size: 0.85rem; margin-bottom: 6px; display: block; }
.reg-input-group { display: flex; align-items: stretch; border-radius: 12px; overflow: hidden; border: 2px solid #e9ecef; background: #f8fafc; transition: all 0.3s; }
.reg-input-group:focus-within { border-color: #2d6a9f; box-shadow: 0 0 0 3px rgba(45, 106, 159, 0.15); background: #fff; }
.reg-input-icon { display: flex; align-items: center; justify-content: center; padding: 0 14px; color: #2d6a9f; font-size: 0.95rem; border-right: 1px solid #e9ecef; flex-shrink: 0; }
.reg-input { flex: 1; border: none; background: transparent; padding: 11px 14px; font-size: 0.9rem; font-family: inherit; outline: none; color: #1e293b; width: 100%; }
.reg-input::placeholder { color: #94a3b8; }
.reg-check-inline { display: flex; align-items: center; padding: 0 14px; border-left: 1px solid #e9ecef; background: #fff; flex-shrink: 0; gap: 6px; }
.reg-check-inline input { transform: scale(1.15); cursor: pointer; }
.reg-check-inline label { font-size: 0.83rem; cursor: pointer; user-select: none; margin: 0; font-weight: 600; color: #475569; }
.reg-help { color: #64748b; font-size: 0.72rem; display: block; margin-top: 4px; }

.reg-enf-container { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 14px; padding: 20px; }
.reg-toggle-group { display: flex; gap: 12px; margin-bottom: 20px; }
.reg-toggle-btn { flex: 1; text-align: center; padding: 12px; border-radius: 12px; border: 2px solid #e9ecef; background: #fff; font-weight: 700; font-size: 0.88rem; cursor: pointer; transition: all 0.25s; color: #475569; margin: 0; }
.reg-toggle-btn:hover { border-color: #2d6a9f; color: #2d6a9f; }
.btn-check:checked + .reg-toggle-btn { border-color: #64748b; background: #64748b; color: #fff; }
.btn-check:checked + .reg-toggle-si { border-color: #2d6a9f; background: linear-gradient(135deg, #1a3a5c, #2d6a9f); color: #fff; box-shadow: 0 6px 18px rgba(45, 106, 159, 0.3); }

.reg-check-card { background: #fff; border: 2px solid #e9ecef; border-radius: 12px; padding: 12px 16px; transition: all 0.2s; cursor: pointer; display: flex; align-items: center; gap: 10px; margin: 0; width: 100%; }
.reg-check-card:hover { border-color: #2d6a9f; }
.reg-check-card input { transform: scale(1.15); cursor: pointer; margin: 0; }
.reg-check-card span { font-weight: 700; color: #1a3a5c; font-size: 0.86rem; }

.reg-modal-footer { background: #f8fafc; border-top: 1px solid #e9ecef; padding: 18px 30px; display: flex; justify-content: flex-end; gap: 12px; }
.reg-btn-cancel { background: #fff; color: #475569; border: 2px solid #e2e8f0; padding: 11px 26px; border-radius: 50px; font-weight: 700; cursor: pointer; transition: all 0.25s; font-size: 0.9rem; }
.reg-btn-cancel:hover { background: #f1f5f9; color: #1a3a5c; border-color: #cbd5e1; }
.reg-btn-save { background: linear-gradient(135deg, #1a3a5c 0%, #2d6a9f 100%); color: #fff; border: none; padding: 11px 26px; border-radius: 50px; font-weight: 700; cursor: pointer; transition: all 0.25s; font-size: 0.9rem; box-shadow: 0 6px 18px rgba(45, 106, 159, 0.3); }
.reg-btn-save:hover { transform: translateY(-2px); box-shadow: 0 10px 26px rgba(45, 106, 159, 0.45); color: #fff; }

.receta-header { margin-bottom: 25px; }
.receta-header-content { background: linear-gradient(135deg, #1a365d 0%, #2563eb 50%, #3b82f6 100%); border-radius: 20px; padding: 25px 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; box-shadow: 0 15px 40px rgba(37, 99, 235, 0.3); }
.receta-header-left { display: flex; align-items: center; gap: 15px; }
.receta-icon-wrapper { background: rgba(255,255,255,.2); border-radius: 50%; padding: 15px; font-size: 28px; color: #fff; }
.receta-title { font-size: 28px; font-weight: 700; margin: 0; color: #fff; text-shadow: 0 2px 10px rgba(0,0,0,.3); }
.receta-subtitle { margin: 5px 0 0; color: rgba(255,255,255,.9); font-size: 16px; }
.receta-divider { margin: 0 10px; opacity: .5; }
.receta-header-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.receta-btn-light { background: rgba(255,255,255,0.95); color: #1a365d; padding: 10px 25px; border-radius: 50px; text-decoration: none; font-weight: 700; box-shadow: 0 4px 15px rgba(0,0,0,.2); transition: all .3s; display: inline-block; border: none; cursor: pointer; font-size: 14px; }
.receta-btn-light:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,.3); }
.receta-badge { background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); color: #fff; padding: 8px 20px; border-radius: 50px; font-weight: 700; font-size: 14px; border: 1px solid rgba(255,255,255,0.2); }

.receta-card { background: linear-gradient(135deg, #1e293b, #2d3748); border-radius: 20px; padding: 30px; border: 1px solid rgba(59, 130, 246, 0.2); box-shadow: 0 20px 50px rgba(0,0,0,.3); }
.receta-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
.receta-card-title { color: #60a5fa; margin: 0; font-weight: 700; font-size: 22px; }
.receta-card-badge { background: linear-gradient(135deg, #2563eb, #3b82f6); color: #fff; padding: 8px 20px; border-radius: 50px; font-weight: 700; font-size: 14px; box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3); }

.receta-item { background: linear-gradient(135deg, #0f172a, #1a2332); border-radius: 15px; padding: 20px; margin-bottom: 15px; border: 1px solid rgba(59, 130, 246, 0.1); }
.receta-item-grid { display: grid; grid-template-columns: 1fr 1fr .5fr .3fr; gap: 15px; align-items: end; }
.receta-label { color: #94a3b8; font-size: 13px; font-weight: 600; display: block; margin-bottom: 5px; }
.receta-select { width: 100%; padding: 12px; border-radius: 10px; border: 2px solid #3b82f6; background: #0f172a; color: #e2e8f0; font-size: 14px; font-family: inherit; cursor: pointer; transition: all .3s; }
.receta-select:focus { outline: none; border-color: #60a5fa; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); }
.receta-select optgroup { font-weight: 700; color: #93c5fd; background: #1e293b; }
.receta-select option { background: #0f172a; color: #e2e8f0; }
.receta-input { width: 100%; padding: 12px; border-radius: 10px; border: 2px solid #3b82f6; background: #0f172a; color: #e2e8f0; font-size: 14px; transition: all .3s; font-family: inherit; }
.receta-input:focus { outline: none; border-color: #60a5fa; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); }
.receta-input::placeholder { color: #64748b; }
.receta-item-actions { display: flex; align-items: flex-end; justify-content: flex-end; }
.receta-btn-delete { background: linear-gradient(135deg, #dc2626, #ef4444); color: #fff; border: none; padding: 12px 20px; border-radius: 10px; cursor: pointer; font-size: 16px; transition: all .3s; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.2); }
.receta-btn-delete:hover { transform: scale(1.05); box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3); }
.receta-btn-add { background: linear-gradient(135deg, #059669, #10b981); color: #fff; border: none; padding: 12px 30px; border-radius: 50px; font-weight: 700; font-size: 15px; cursor: pointer; transition: all .3s; margin-top: 10px; box-shadow: 0 4px 15px rgba(5, 150, 105, 0.3); }
.receta-btn-add:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(5, 150, 105, 0.4); }
.receta-observaciones { margin: 20px 0; }
.receta-textarea { width: 100%; padding: 14px; border-radius: 12px; border: 2px solid #3b82f6; background: #0f172a; color: #e2e8f0; font-size: 15px; resize: vertical; transition: all .3s; font-family: inherit; }
.receta-textarea:focus { outline: none; border-color: #60a5fa; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); }
.receta-textarea::placeholder { color: #64748b; }
.receta-help { color: #64748b; font-size: 13px; display: block; margin-top: 5px; }
.receta-divider-full { border: none; border-top: 2px solid rgba(59, 130, 246, 0.2); margin: 25px 0; }
.receta-firma { background: linear-gradient(135deg, #0f172a, #1a2332); border-radius: 15px; padding: 20px; border: 2px solid #3b82f6; margin-bottom: 25px; display: flex; align-items: center; }
.receta-firma-icon { background: linear-gradient(135deg, #2563eb, #3b82f6); border-radius: 50%; padding: 15px; margin-right: 15px; color: #fff; font-size: 24px; box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3); }
.receta-firma-label { color: #94a3b8; font-size: 12px; font-weight: 700; text-transform: uppercase; display: block; }
.receta-firma-code { color: #e2e8f0; font-size: 14px; }
.receta-btn-submit { width: 100%; padding: 18px; border-radius: 50px; border: none; font-size: 18px; font-weight: 700; color: #fff; background: linear-gradient(135deg, #1a365d 0%, #2563eb 50%, #3b82f6 100%); cursor: pointer; transition: all .3s; box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3); }
.receta-btn-submit:hover { transform: translateY(-2px); box-shadow: 0 12px 35px rgba(37, 99, 235, 0.5); }
.receta-btn-submit:disabled { opacity: .5; cursor: not-allowed; transform: none !important; }
.receta-input.is-invalid, .receta-select.is-invalid { border-color: #ef4444 !important; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2) !important; }

@media (max-width: 768px) {
    .receta-item-grid { grid-template-columns: 1fr 1fr; }
    .receta-item-actions { grid-column: span 2; justify-content: flex-end; }
    .receta-header-content { flex-direction: column; text-align: center; }
    .receta-header-left { flex-direction: column; text-align: center; }
    .receta-title { font-size: 22px; }
    .receta-container { padding: 15px; }
    .receta-card { padding: 20px; }
    .receta-modal-table th, .receta-modal-table td { padding: 10px 12px; font-size: 13px; }
    .reg-modal-body { padding: 20px; min-height: auto; }
    .reg-modal-header { padding: 18px 20px; }
    .reg-toggle-group { flex-direction: column; }
    .reg-modal-footer { flex-wrap: wrap; }
}
@media (max-width: 480px) {
    .receta-item-grid { grid-template-columns: 1fr; }
    .receta-item-actions { grid-column: span 1; justify-content: center; }
}
</style>


<script>
function validarStock(el) {
    const fila = el.closest('.receta-item');
    if (!fila) return;
    const select = fila.querySelector('.sel-med');
    const input = fila.querySelector('.inp-cant');
    if (!select || !input) return;
    if (select.value === "") { input.classList.remove('is-invalid'); actualizarBoton(); return; }
    const opcionSeleccionada = select.options[select.selectedIndex];
    const stock = parseInt(opcionSeleccionada.dataset.stock) || 0;
    const cant = parseInt(input.value) || 0;
    if (stock <= 0 || cant <= 0 || cant > stock) input.classList.add('is-invalid');
    else input.classList.remove('is-invalid');
    actualizarBoton();
}

function actualizarBoton() {
    const btn = document.getElementById('btnGuardar');
    if (!btn) return;
    const errores = document.querySelectorAll('.is-invalid').length;
    btn.disabled = errores > 0;
}

function agregarItem() {
    const contenedor = document.getElementById('contenedor-items');
    const filas = contenedor.querySelectorAll('.receta-item');
    const index = filas.length;
    const primeraFila = contenedor.querySelector('.receta-item');
    if (!primeraFila) return;
    const nuevaFila = primeraFila.cloneNode(true);
    nuevaFila.querySelectorAll('input, select').forEach(function(elemento) {
        elemento.value = "";
        elemento.classList.remove('is-invalid');
        const nombre = elemento.getAttribute('name');
        if (nombre) elemento.setAttribute('name', nombre.replace(/\[\d+\]/, '[' + index + ']'));
    });
    contenedor.appendChild(nuevaFila);
    actualizarBoton();
}

function eliminarFila(btn) {
    const filas = document.querySelectorAll('.receta-item');
    if (filas.length <= 1) { alert('Debe mantener al menos un medicamento.'); return; }
    const fila = btn.closest('.receta-item');
    if (fila) fila.remove();
    actualizarBoton();
}

document.addEventListener('DOMContentLoaded', function() {

    const formulario = document.getElementById('formReceta');
    if (formulario) {
        formulario.addEventListener('submit', function(event) {
            const filas = document.querySelectorAll('.receta-item');
            let valido = true;
            filas.forEach(function(fila) {
                const select = fila.querySelector('.sel-med');
                const cantidad = fila.querySelector('.inp-cant');
                if (!select || !cantidad) return;
                if (select.value === "") { select.classList.add('is-invalid'); valido = false; return; }
                const opcion = select.options[select.selectedIndex];
                const stock = parseInt(opcion.dataset.stock) || 0;
                const cant = parseInt(cantidad.value) || 0;
                if (cant <= 0 || cant > stock) { cantidad.classList.add('is-invalid'); valido = false; }
                else cantidad.classList.remove('is-invalid');
            });
            if (!valido) { event.preventDefault(); alert('Revise los medicamentos y las cantidades seleccionadas.'); actualizarBoton(); }
        });
    }

    const inputBuscar = document.getElementById('buscarPacienteInput');
    if (inputBuscar) {
        inputBuscar.addEventListener('input', function() {
            const filtro = this.value.toLowerCase().trim();
            const filas = document.querySelectorAll('#tbodyPacientesModal tr[data-busqueda]');
            let visibles = 0;
            filas.forEach(function(fila) {
                const texto = fila.getAttribute('data-busqueda') || '';
                if (texto.includes(filtro)) { fila.style.display = ''; visibles++; }
                else fila.style.display = 'none';
            });
            const sinResultados = document.getElementById('sinResultadosModal');
            const tablaWrapper = document.querySelector('.receta-modal-table-wrapper');
            if (visibles === 0 && filtro !== '') { sinResultados.style.display = 'block'; tablaWrapper.style.display = 'none'; }
            else { sinResultados.style.display = 'none'; tablaWrapper.style.display = 'block'; }
        });
        const modalBuscar = document.getElementById('modalBuscarPaciente');
        if (modalBuscar) modalBuscar.addEventListener('shown.bs.modal', function() { inputBuscar.focus(); });
    }

    const checkWhatsApp = document.getElementById('checkWhatsApp');
    const tutorFields = document.getElementById('tutorFields');
    if (checkWhatsApp && tutorFields) {
        checkWhatsApp.addEventListener('change', function() {
            if (this.checked) {
                tutorFields.classList.add('d-none');
            } else {
                tutorFields.classList.remove('d-none');
            }
        });
    }

    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const btnSiguiente = document.getElementById('btnSiguiente');
    const btnAtras = document.getElementById('btnAtras');
    const btnRegistrar = document.getElementById('btnRegistrar');
    const progressBar = document.getElementById('progressBar');
    const pasoActual = document.getElementById('pasoActual');

    if (step1 && step2 && btnSiguiente && btnAtras && btnRegistrar) {
        function irAPaso(paso) {
            if (paso === 1) {
                step1.classList.remove('d-none');
                step2.classList.add('d-none');
                btnSiguiente.classList.remove('d-none');
                btnAtras.classList.add('d-none');
                btnRegistrar.classList.add('d-none');
                progressBar.style.width = '50%';
                pasoActual.textContent = '1';
            } else if (paso === 2) {
                step1.classList.add('d-none');
                step2.classList.remove('d-none');
                btnSiguiente.classList.add('d-none');
                btnAtras.classList.remove('d-none');
                btnRegistrar.classList.remove('d-none');
                progressBar.style.width = '100%';
                pasoActual.textContent = '2';
            }
        }

        btnSiguiente.addEventListener('click', function() {
            const nombre = document.getElementById('reg_nombre');
            const apellido = document.getElementById('reg_apellido');
            const fechaNac = document.getElementById('fechaNacimientoModal');
            let valido = true;

            let camposAValidar = [nombre, apellido, fechaNac];

            if (checkWhatsApp && !checkWhatsApp.checked) {
                const nombreTutor = document.getElementById('reg_nombre_tutor');
                const telTutor = document.getElementById('reg_telefono_tutor');
                if (nombreTutor) camposAValidar.push(nombreTutor);
                if (telTutor) camposAValidar.push(telTutor);
            }

            camposAValidar.forEach(function(campo) {
                if (campo && !campo.value.trim()) {
                    campo.style.borderColor = '#ef4444';
                    campo.style.boxShadow = '0 0 0 3px rgba(239,68,68,0.15)';
                    valido = false;
                } else if (campo) {
                    campo.style.borderColor = '';
                    campo.style.boxShadow = '';
                }
            });

            if (!valido) { alert('Complete los campos obligatorios.'); return; }
            irAPaso(2);
        });

        btnAtras.addEventListener('click', function() { irAPaso(1); });

        const modalReg = document.getElementById('modalRegistrarPaciente');
        if (modalReg) {
            modalReg.addEventListener('hidden.bs.modal', function() {
                irAPaso(1);
                const formReg = document.getElementById('formRegistrarPaciente');
                if (formReg) formReg.reset();
                const contEnf = document.getElementById('contenedorEnfermedadesModal');
                if (contEnf) contEnf.classList.add('d-none');
                const otraInput = document.getElementById('otra_enfermedad_modal');
                if (otraInput) { otraInput.style.display = 'none'; otraInput.value = ''; }
                if (tutorFields) tutorFields.classList.add('d-none');
            });
        }
    }

    const fechaNacimiento = document.getElementById('fechaNacimientoModal');
    const edadInput = document.getElementById('edadModal');
    if (fechaNacimiento && edadInput) {
        fechaNacimiento.addEventListener('change', function() {
            if (this.value) {
                const hoy = new Date();
                const nacimiento = new Date(this.value);
                let edad = hoy.getFullYear() - nacimiento.getFullYear();
                const mes = hoy.getMonth() - nacimiento.getMonth();
                if (mes < 0 || (mes === 0 && hoy.getDate() < nacimiento.getDate())) edad--;
                edadInput.value = edad >= 0 ? edad : 0;
            }
        });
        edadInput.addEventListener('input', function() {
            const edad = parseInt(this.value);
            if (!isNaN(edad) && edad >= 0 && edad <= 120) {
                const hoy = new Date();
                const nuevaFecha = new Date(hoy.getFullYear() - edad, hoy.getMonth(), hoy.getDate());
                const year = nuevaFecha.getFullYear();
                const month = String(nuevaFecha.getMonth() + 1).padStart(2, '0');
                const day = String(nuevaFecha.getDate()).padStart(2, '0');
                fechaNacimiento.value = `${year}-${month}-${day}`;
            }
        });
    }

    const cronicaSiModal = document.getElementById('cronicaSiModal');
    const cronicaNoModal = document.getElementById('cronicaNoModal');
    const contenedorEnfermedadesModal = document.getElementById('contenedorEnfermedadesModal');
    const otraCheckModal = document.getElementById('enf_otra_modal');
    const otraInputModal = document.getElementById('otra_enfermedad_modal');

    function toggleEnfermedadesModal() {
        if (cronicaSiModal && cronicaSiModal.checked) {
            contenedorEnfermedadesModal.classList.remove('d-none');
        } else {
            contenedorEnfermedadesModal.classList.add('d-none');
            document.querySelectorAll('.enfermedad-check-modal').forEach(cb => cb.checked = false);
            if (otraCheckModal) otraCheckModal.checked = false;
            if (otraInputModal) { otraInputModal.style.display = 'none'; otraInputModal.value = ''; }
        }
    }

    if (cronicaSiModal && cronicaNoModal) {
        cronicaSiModal.addEventListener('change', toggleEnfermedadesModal);
        cronicaNoModal.addEventListener('change', toggleEnfermedadesModal);
    }
    if (otraCheckModal && otraInputModal) {
        otraCheckModal.addEventListener('change', function() {
            if (this.checked) { otraInputModal.style.display = 'block'; otraInputModal.focus(); }
            else { otraInputModal.style.display = 'none'; otraInputModal.value = ''; }
        });
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>