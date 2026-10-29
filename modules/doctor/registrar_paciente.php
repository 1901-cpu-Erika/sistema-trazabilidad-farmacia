<?php
require_once '../../config/database.php';
$titulo_pagina = "Registrar Paciente";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

if ($_SESSION['tipo_usuario'] != 'doctor') {
    header("Location: ../../login.php");
    exit();
}

$mensaje = '';
$tipo_mensaje = '';
$paciente_registrado = null;

$conn->query("
    CREATE TABLE IF NOT EXISTS enfermedades_cronicas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL UNIQUE,
        estado TINYINT DEFAULT 1
    )
");

$conn->query("
    CREATE TABLE IF NOT EXISTS paciente_enfermedades (
        paciente_id INT,
        enfermedad_id INT,
        PRIMARY KEY (paciente_id, enfermedad_id)
    )
");

$enfermedades_default = [
    'Hipertensión Arterial',
    'Diabetes Mellitus',
    'Asma',
    'Cardiopatía',
    'Enfermedad Renal Crónica'
];

foreach ($enfermedades_default as $enf) {
    $stmt = $conn->prepare("INSERT IGNORE INTO enfermedades_cronicas (nombre) VALUES (?)");
    $stmt->bind_param("s", $enf);
    $stmt->execute();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = $_POST['nombre'];
    $apellido = $_POST['apellido'];
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $sexo = $_POST['sexo'];
    $telefono = $_POST['telefono'];
    $tiene_whatsapp = isset($_POST['tiene_whatsapp']) ? 1 : 0;
    $cedula = $_POST['cedula'] ?? null;

    $responsable_nombre = $_POST['responsable_nombre'] ?? null;
    $responsable_telefono = $_POST['responsable_telefono'] ?? null;
    $responsable_cedula = $_POST['responsable_cedula'] ?? null;

    $nombre_tutor = $_POST['nombre_tutor'] ?? null;
    $telefono_tutor = $_POST['telefono_tutor'] ?? null;

    $contacto_nombre = $_POST['contacto_nombre'] ?? null;
    $contacto_telefono = $_POST['contacto_telefono'] ?? null;
    $contacto_whatsapp = isset($_POST['contacto_whatsapp']) ? 1 : 0;

    $enfermedades_seleccionadas = $_POST['enfermedades'] ?? [];
    $otra_enfermedad_text = trim($_POST['otra_enfermedad'] ?? '');

    $enfermedades_ids = [];

    foreach ($enfermedades_seleccionadas as $item) {
        if ($item === 'otra') {
            if (empty($otra_enfermedad_text)) {
                $errores[] = "Debe especificar el nombre de la enfermedad 'Otra'.";
            } else {
                $buscar = $conn->prepare("SELECT id FROM enfermedades_cronicas WHERE nombre = ?");
                $buscar->bind_param("s", $otra_enfermedad_text);
                $buscar->execute();
                $res = $buscar->get_result();
                if ($fila = $res->fetch_assoc()) {
                    $enfermedades_ids[] = $fila['id'];
                } else {
                    $insertar = $conn->prepare("INSERT INTO enfermedades_cronicas (nombre) VALUES (?)");
                    $insertar->bind_param("s", $otra_enfermedad_text);
                    if ($insertar->execute()) {
                        $enfermedades_ids[] = $conn->insert_id;
                    } else {
                        $errores[] = "Error al guardar la nueva enfermedad.";
                    }
                }
            }
        } else {
            $nombre_enfermedad = trim($item);
            $buscar = $conn->prepare("SELECT id FROM enfermedades_cronicas WHERE nombre = ?");
            $buscar->bind_param("s", $nombre_enfermedad);
            $buscar->execute();
            $res = $buscar->get_result();
            if ($fila = $res->fetch_assoc()) {
                $enfermedades_ids[] = $fila['id'];
            }
        }
    }

    $errores = [];
    $edad = null;

    if (!empty($fecha_nacimiento)) {
        $nacimiento = new DateTime($fecha_nacimiento);
        $hoy = new DateTime();
        $edad = $hoy->diff($nacimiento)->y;

        $requiere_responsable = ($edad < 18);
        if (!$requiere_responsable && $tiene_whatsapp == 0 && !empty($telefono)) {
            $requiere_responsable = true;
        }

        if ($requiere_responsable) {
            if (empty($responsable_nombre) || empty($responsable_telefono) || empty($responsable_cedula)) {
                $errores[] = "Se requiere completar el nombre, cédula y teléfono del responsable.";
            }
        }

        if ($edad > 50 && empty($telefono)) {
            if (empty($contacto_nombre) || empty($contacto_telefono)) {
                $errores[] = "El paciente es mayor de 50 años y no tiene teléfono. Debe completar el contacto alternativo.";
            }
        }
    } else {
        $errores[] = "Debe ingresar la fecha de nacimiento.";
    }

    if (!empty($errores)) {
        $mensaje = implode("<br>", $errores);
        $tipo_mensaje = "danger";
    } else {
        $conn->begin_transaction();
        try {
            if (empty($edad) && !empty($fecha_nacimiento)) {
                $nacimiento = new DateTime($fecha_nacimiento);
                $hoy = new DateTime();
                $edad = $hoy->diff($nacimiento)->y;
            }
            $edad = (int)$edad;

            $sql = "INSERT INTO pacientes 
                        (nombre, apellido, fecha_nacimiento, edad, sexo, telefono, tiene_whatsapp, cedula, 
                         responsable_nombre, responsable_telefono, responsable_cedula, 
                         contacto_alternativo_nombre, contacto_alternativo_telefono, contacto_alternativo_whatsapp,
                         nombre_tutor, telefono_tutor) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception("Error en la preparación: " . $conn->error);

            $stmt->bind_param(
                "sssisissssssssss", 
                $nombre,
                $apellido,
                $fecha_nacimiento,
                $edad,
                $sexo,
                $telefono,
                $tiene_whatsapp,
                $cedula,
                $responsable_nombre,
                $responsable_telefono,
                $responsable_cedula,
                $contacto_nombre,
                $contacto_telefono,
                $contacto_whatsapp,
                $nombre_tutor,
                $telefono_tutor
            );

            if (!$stmt->execute()) throw new Exception("Error al insertar paciente: " . $stmt->error);

            $paciente_id = $conn->insert_id;
            $stmt->close();

            if (!empty($enfermedades_ids)) {
                $sql_rel = "INSERT INTO paciente_enfermedades (paciente_id, enfermedad_id) VALUES (?, ?)";
                $stmt_rel = $conn->prepare($sql_rel);
                if (!$stmt_rel) throw new Exception("Error preparando relación enfermedades: " . $conn->error);

                foreach ($enfermedades_ids as $eid) {
                    $stmt_rel->bind_param("ii", $paciente_id, $eid);
                    if (!$stmt_rel->execute()) {
                        throw new Exception("Error al guardar enfermedad: " . $stmt_rel->error);
                    }
                }
                $stmt_rel->close();
            }

            $conn->commit();
            $paciente_registrado = $paciente_id;
            $mensaje = "Paciente registrado correctamente.";
            $tipo_mensaje = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $mensaje = "Error: " . $e->getMessage();
            $tipo_mensaje = "danger";
        }
    }
}
?>

<style>
    .form-container {
        max-width: 900px;
        margin: 0 auto;
    }

    .banner-form {
        background: linear-gradient(135deg, #1a3a5c 0%, #2d6a9f 100%);
        border-radius: 20px;
        padding: 30px;
        color: white;
        margin-bottom: 30px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(26, 58, 92, 0.3);
    }

    .banner-form i.bg-icon {
        position: absolute;
        right: 20px;
        bottom: 10px;
        font-size: 150px;
        opacity: 0.1;
        color: white;
    }

    .card-modern {
        background: white;
        border-radius: 20px;
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
        border: none;
    }

    .form-label {
        font-weight: 600;
        color: #1a3a5c;
        font-size: 0.9rem;
        margin-bottom: 8px;
    }

    .form-control, .form-select {
        border: 2px solid #e9ecef;
        border-radius: 12px;
        padding: 12px 15px;
        font-size: 0.95rem;
        transition: all 0.3s;
    }

    .form-control:focus, .form-select:focus {
        border-color: #2d6a9f;
        box-shadow: 0 0 0 0.2rem rgba(45, 106, 159, 0.15);
    }

    .section-title {
        color: #1a3a5c;
        font-size: 1.1rem;
        font-weight: 700;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e9ecef;
        display: flex;
        align-items: center;
    }

    .section-title i {
        margin-right: 10px;
        color: #2d6a9f;
    }

    .alert-box {
        border-radius: 15px;
        padding: 20px;
        margin-top: 20px;
    }

    .alert-warning-box {
        background: #fff8e1;
        border: 2px solid #ffc107;
    }

    .alert-danger-box {
        background: #fdf2f2;
        border: 2px solid #dc3545;
    }

    .btn-toggle-group {
        gap: 10px;
    }

    .btn-toggle-group .btn {
        border-radius: 12px;
        padding: 12px;
        font-weight: 600;
    }

    .checkbox-card {
        background: white;
        border: 2px solid #e9ecef;
        border-radius: 12px;
        padding: 12px;
        transition: all 0.2s;
        cursor: pointer;
    }

    .checkbox-card:hover {
        border-color: #2d6a9f;
    }

    .checkbox-card input[type="checkbox"] {
        margin-right: 8px;
        transform: scale(1.2);
    }

    .btn-submit {
        background: linear-gradient(135deg, #1a3a5c, #2d6a9f);
        color: white;
        border: none;
        border-radius: 12px;
        padding: 15px;
        font-size: 1.1rem;
        font-weight: 700;
        transition: all 0.3s;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(45, 106, 159, 0.4);
        color: white;
    }

    .btn-back {
        border-radius: 12px;
        padding: 15px;
        font-weight: 600;
    }

    .fade-in {
        animation: fadeUp 0.5s ease forwards;
    }

    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    #contenedorEnfermedades {
        animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-5px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="container-fluid fade-in">
    
    <div class="banner-form">
        <i class="fas fa-user-plus bg-icon"></i>
        <div class="d-flex align-items-center">
            <div class="rounded-circle bg-white bg-opacity-25 p-3 me-3">
                <i class="fas fa-user-plus fa-2x"></i>
            </div>
            <div>
                <h2 class="fw-bold mb-1">Registro de Pacientes</h2>
                <p class="mb-0 opacity-75">Complete la información del paciente para darle de alta en el sistema de trazabilidad.</p>
            </div>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show shadow-sm border-0 rounded-3" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($paciente_registrado): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-3">
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <i class="fas fa-check-circle fa-3x text-success"></i>
                </div>
                <div>
                    <h5 class="alert-heading fw-bold mb-1">¡Paciente registrado con éxito!</h5>
                    <p class="mb-2">El paciente ya está guardado en la base de datos.</p>
                    <div class="mt-2">
                        <a href="crear_receta.php?paciente_id=<?php echo $paciente_registrado; ?>" class="btn btn-success btn-sm rounded-pill px-3">
                            <i class="fas fa-file-prescription"></i> Crear Receta AHORA
                        </a>
                        <a href="buscar_paciente.php" class="btn btn-outline-primary btn-sm rounded-pill px-3 ms-2">
                            <i class="fas fa-search"></i> Buscar en el Sistema
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card-modern p-4 p-md-5">
        <form method="POST" action="">
            
            <div class="section-title">
                <i class="fas fa-id-card"></i> Datos Personales
            </div>

            <div class="row">
                <div class="col-md-6 mb-4">
                    <label for="nombre" class="form-label">Nombre *</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="fas fa-user text-primary"></i></span>
                        <input type="text" class="form-control border-start-0" id="nombre" name="nombre" required>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <label for="apellido" class="form-label">Apellido *</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="fas fa-user text-primary"></i></span>
                        <input type="text" class="form-control border-start-0" id="apellido" name="apellido" required>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-4" id="cedula_container">
                    <label for="cedula" class="form-label">Cédula</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="fas fa-id-card text-primary"></i></span>
                        <input type="text" class="form-control border-start-0" id="cedula" name="cedula" placeholder="001-000000-0000X">
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento *</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="fas fa-calendar-alt text-primary"></i></span>
                        <input type="date" class="form-control border-start-0" id="fecha_nacimiento" name="fecha_nacimiento" required>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <label for="edad" class="form-label">Edad (Años)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="fas fa-birthday-cake text-primary"></i></span>
                        <input type="number" class="form-control border-start-0" id="edad" name="edad" placeholder="0">
                    </div>
                    <small class="text-muted">Si escribe la edad, la fecha se calcula sola.</small>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-4">
                    <label for="sexo" class="form-label">Sexo</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="fas fa-venus-mars text-primary"></i></span>
                        <select class="form-select border-start-0" id="sexo" name="sexo">
                            <option value="">Seleccione</option>
                            <option value="M">Masculino</option>
                            <option value="F">Femenino</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <label for="telefono" class="form-label">Teléfono</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="fas fa-phone-alt text-primary"></i></span>
                        <input type="text" class="form-control border-start-0" id="telefono" name="telefono" placeholder="Ej: 1234-5678">
                        <div class="input-group-text bg-white p-2 border-start-0">
                            <div class="form-check mb-0 d-flex align-items-center">
                                <input class="form-check-input mt-0 me-2" type="checkbox" name="tiene_whatsapp" id="tiene_whatsapp" value="1" checked>
                                <label class="form-check-label" for="tiene_whatsapp" style="cursor:pointer; user-select: none; font-size: 0.95rem;">
                                    <i class="fab fa-whatsapp text-success me-1"></i> WhatsApp
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="responsable_div" class="alert-box alert-warning-box" style="display: none;">
                <div class="section-title text-warning">
                    <i class="fas fa-user-shield"></i> Responsable (Contacto Principal)
                </div>
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Nombre del Responsable *</label>
                        <input type="text" class="form-control" name="responsable_nombre" id="responsable_nombre">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Cédula del Responsable *</label>
                        <input type="text" class="form-control" name="responsable_cedula" id="responsable_cedula">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Teléfono del Responsable *</label>
                        <input type="text" class="form-control" name="responsable_telefono" id="responsable_telefono">
                    </div>
                </div>
            </div>

            <div id="contacto_alternativo_div" class="alert-box alert-danger-box" style="display: none;">
                <div class="section-title text-danger">
                    <i class="fas fa-exclamation-triangle"></i> Contacto Alternativo (Mayor de 50 años sin teléfono)
                </div>
                <div class="row">
                    <div class="col-md-5 mb-2">
                        <label class="form-label">Nombre de Contacto *</label>
                        <input type="text" class="form-control" name="contacto_nombre" id="contacto_nombre">
                    </div>
                    <div class="col-md-7 mb-2">
                        <label class="form-label">Teléfono de Contacto *</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="contacto_telefono" id="contacto_telefono">
                            <div class="input-group-text bg-white p-2 border-start-0">
                                <div class="form-check mb-0 d-flex align-items-center">
                                    <input class="form-check-input mt-0 me-2" type="checkbox" name="contacto_whatsapp" value="1" checked>
                                    <label class="form-check-label" style="cursor:pointer; user-select: none; font-size: 0.95rem;">
                                        <i class="fab fa-whatsapp text-success me-1"></i> WhatsApp
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section-title mt-4">
                <i class="fas fa-heartbeat text-danger"></i> Enfermedades Crónicas
            </div>

            <div class="p-4 rounded-4 border border-secondary-subtle bg-light">
                <div class="btn-toggle-group d-flex mb-4">
                    <input type="radio" class="btn-check" name="tiene_enfermedad_cronica" id="cronicaNo" value="0" checked>
                    <label class="btn btn-outline-secondary flex-fill" for="cronicaNo">
                        <i class="fas fa-times-circle me-1"></i> No padece
                    </label>

                    <input type="radio" class="btn-check" name="tiene_enfermedad_cronica" id="cronicaSi" value="1">
                    <label class="btn btn-outline-primary flex-fill" for="cronicaSi">
                        <i class="fas fa-check-circle me-1"></i> Sí padece
                    </label>
                </div>

                <div id="contenedorEnfermedades" class="d-none">
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
                                <label class="checkbox-card d-flex align-items-center w-100">
                                    <input class="form-check-input enfermedad-check" type="checkbox"
                                           name="enfermedades[]" value="<?php echo htmlspecialchars($enfermedad); ?>">
                                    <span class="fw-bold"><?php echo htmlspecialchars($enfermedad); ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="col-md-4 col-sm-6">
                            <label class="checkbox-card d-flex align-items-center w-100" for="enf_otra">
                                <input class="form-check-input" type="checkbox" name="enfermedades[]" value="otra" id="enf_otra">
                                <span class="fw-bold">Otra</span>
                            </label>
                            <input type="text" class="form-control mt-2" id="otra_enfermedad_input"
                                   name="otra_enfermedad" placeholder="Especifique..." style="display:none;">
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-column flex-md-row gap-3 mt-5">
                <a href="buscar_paciente.php" class="btn btn-light border btn-back flex-fill">
                    <i class="fas fa-arrow-left me-2"></i> Volver a Buscar
                </a>
                <button type="submit" class="btn btn-submit flex-fill">
                    <i class="fas fa-save me-2"></i> Registrar Paciente
                </button>
            </div>

        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fechaInput = document.getElementById('fecha_nacimiento');
    const edadInput = document.getElementById('edad');
    const telefonoInput = document.getElementById('telefono');
    const responsableDiv = document.getElementById('responsable_div');
    const contactoDiv = document.getElementById('contacto_alternativo_div');
    const cedulaContainer = document.getElementById('cedula_container');
    const whatsappCheck = document.getElementById('tiene_whatsapp');

    const cronicaSi = document.getElementById('cronicaSi');
    const cronicaNo = document.getElementById('cronicaNo');
    const contenedorEnfermedades = document.getElementById('contenedorEnfermedades');
    const otraCheck = document.getElementById('enf_otra');
    const otraInput = document.getElementById('otra_enfermedad_input');

    function calcularEdad(fechaISO) {
        if (!fechaISO) return null;
        const hoy = new Date();
        const nac = new Date(fechaISO);
        let edad = hoy.getFullYear() - nac.getFullYear();
        const mes = hoy.getMonth() - nac.getMonth();
        if (mes < 0 || (mes === 0 && hoy.getDate() < nac.getDate())) edad--;
        return edad;
    }

    function calcularFechaDesdeEdad(edad) {
        if (!edad || isNaN(edad) || edad < 0) return '';
        const hoy = new Date();
        const nuevaFecha = new Date(hoy.getFullYear() - edad, hoy.getMonth(), hoy.getDate());
        const year = nuevaFecha.getFullYear();
        const month = String(nuevaFecha.getMonth() + 1).padStart(2, '0');
        const day = String(nuevaFecha.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    fechaInput.addEventListener('change', function() {
        const edad = calcularEdad(this.value);
        if (edad !== null) {
            edadInput.value = edad;
        }
        evaluarFormulario();
    });

    edadInput.addEventListener('input', function() {
        const edad = parseInt(this.value);
        if (!isNaN(edad) && edad >= 0 && edad <= 120) {
            const fechaBack = calcularFechaDesdeEdad(edad);
            fechaInput.value = fechaBack;
            fechaInput.dispatchEvent(new Event('change'));
        }
    });

    function evaluarFormulario() {
        const edad = calcularEdad(fechaInput.value);
        const hasPhone = telefonoInput.value.trim() !== '';
        const hasWhatsApp = whatsappCheck.checked;
        const isMinor = edad !== null && edad < 18;

        if (edad !== null && edad > 50 && !hasPhone) {
            contactoDiv.style.display = 'block';
        } else {
            contactoDiv.style.display = 'none';
            document.getElementById('contacto_nombre').value = '';
            document.getElementById('contacto_telefono').value = '';
        }

        if (isMinor) {
            responsableDiv.style.display = 'block';
            cedulaContainer.style.display = 'none';
        } else if (!hasWhatsApp && hasPhone) {
            responsableDiv.style.display = 'block';
            cedulaContainer.style.display = 'block';
        } else {
            responsableDiv.style.display = 'none';
            if (!isMinor) {
                cedulaContainer.style.display = 'block';
            }
            document.getElementById('responsable_nombre').value = '';
            document.getElementById('responsable_cedula').value = '';
            document.getElementById('responsable_telefono').value = '';
        }
    }

    function toggleEnfermedades() {
        if (cronicaSi.checked) {
            contenedorEnfermedades.classList.remove('d-none');
        } else {
            contenedorEnfermedades.classList.add('d-none');
            document.querySelectorAll('.enfermedad-check').forEach(cb => cb.checked = false);
            document.getElementById('enf_otra').checked = false;
            document.getElementById('otra_enfermedad_input').style.display = 'none';
            document.getElementById('otra_enfermedad_input').value = '';
        }
    }

    cronicaSi.addEventListener('change', toggleEnfermedades);
    cronicaNo.addEventListener('change', toggleEnfermedades);

    otraCheck.addEventListener('change', function() {
        if (this.checked) {
            otraInput.style.display = 'block';
            otraInput.focus();
        } else {
            otraInput.style.display = 'none';
            otraInput.value = '';
        }
    });

    whatsappCheck.addEventListener('change', evaluarFormulario);
    telefonoInput.addEventListener('input', evaluarFormulario);

    if (fechaInput.value) {
        const edad = calcularEdad(fechaInput.value);
        if (edad !== null) edadInput.value = edad;
    }
    evaluarFormulario();
});
</script>

<?php require_once '../../includes/footer.php'; ?>