<?php
require_once '../../config/database.php';
$titulo_pagina = "Detalle de Receta";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: historial_recetas.php");
    exit();
}

$receta_id = intval($_GET['id']);

$sql = "
    SELECT r.*, 
           p.nombre AS paciente_nombre,
           p.apellido AS paciente_apellido,
           p.cedula,
           p.telefono,
           p.fecha_nacimiento,
           p.responsable_nombre,
           p.responsable_telefono,
           p.contacto_alternativo_nombre,
           p.contacto_alternativo_telefono,
           p.tiene_whatsapp,
           u.nombre AS doctor_nombre,
           u.codigo_minsa AS doctor_codigo_minsa,
           r.firma_electronica AS firma_electronica
    FROM recetas r
    JOIN pacientes p ON r.paciente_id = p.id
    JOIN usuarios u ON r.doctor_id = u.id
    WHERE r.id = ?
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Error en la preparación de la consulta: " . $conn->error);
}

$stmt->bind_param("i", $receta_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-danger">Receta no encontrada.</div>';
    require_once '../../includes/footer.php';
    exit();
}

$receta = $result->fetch_assoc();

$fecha_nac = new DateTime($receta['fecha_nacimiento']);
$hoy = new DateTime();
$edad = $hoy->diff($fecha_nac)->y;
$es_menor = ($edad < 18);


$doctor_nombre = trim($receta['doctor_nombre']);
$firma = trim($receta['firma_electronica']);

if (empty($doctor_nombre) && !empty($firma)) {
    $doctor_nombre = $firma;
}

if (empty($doctor_nombre)) {
    $doctor_nombre = 'No especificado';
}

$sql_detalles = "
    SELECT rd.cantidad, m.nombre AS medicamento_nombre
    FROM receta_detalles rd
    JOIN medicamentos m ON rd.medicamento_id = m.id
    WHERE rd.receta_id = ?
";
$stmt_det = $conn->prepare($sql_detalles);
if (!$stmt_det) {
    die("Error en detalles: " . $conn->error);
}
$stmt_det->bind_param("i", $receta_id);
$stmt_det->execute();
$detalles = $stmt_det->get_result();
?>

<div class="container-fluid fade-in">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card border-0 shadow-lg rounded-4 glass-card">
                <div class="card-header bg-primary text-white rounded-top-4 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fas fa-prescription me-2"></i> Detalle de Receta #<?php echo $receta_id; ?></h4>
                        <span class="badge bg-light text-primary rounded-pill px-3 py-2">
                            <?php echo date('d/m/Y', strtotime($receta['fecha_receta'])); ?>
                        </span>
                    </div>
                </div>
                <div class="card-body p-4">

                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-4">
                                <h5 class="text-primary"><i class="fas fa-user me-2"></i>Paciente</h5>
                                <hr>
                                <p><strong>Nombre:</strong> <?php echo htmlspecialchars($receta['paciente_nombre'] . ' ' . $receta['paciente_apellido']); ?></p>
                                <p><strong>Cédula:</strong> <?php echo $receta['cedula'] ?: 'No registrada'; ?></p>
                                <p><strong>Teléfono:</strong> <?php echo $receta['telefono'] ?: 'No registrado'; ?></p>
                                <p><strong>Fecha de nacimiento:</strong> <?php echo date('d/m/Y', strtotime($receta['fecha_nacimiento'])); ?></p>
                                <p><strong>Edad:</strong> <?php echo $edad; ?> años</p>
                                <p><strong>Tipo:</strong>
                                    <?php if ($es_menor): ?>
                                        <span class="badge bg-warning">Menor de edad</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Adulto</span>
                                    <?php endif; ?>
                                </p>
                                <?php if ($es_menor && !empty($receta['responsable_nombre'])): ?>
                                    <p><strong>Responsable:</strong> <?php echo htmlspecialchars($receta['responsable_nombre']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-4">
                                <h5 class="text-success"><i class="fas fa-user-md me-2"></i>Doctor</h5>
                                <hr>
                                <p><strong>Nombre:</strong> 
                                    <?php echo htmlspecialchars($doctor_nombre); ?>
                                </p>
                                <p><strong>Código MINSA (Firma):</strong> 
                                    <?php 
                                    if (!empty($receta['doctor_codigo_minsa'])) {
                                        echo htmlspecialchars($receta['doctor_codigo_minsa']);
                                    } else {
                                        echo '<span class="text-warning">No registrado</span>';
                                    }
                                    ?>
                                </p>
                                <p><strong>Estado:</strong>
                                    <?php
                                    $estado = $receta['estado'];
                                    $badgeClass = match($estado) {
                                        'entregada' => 'success',
                                        'aprobada' => 'primary',
                                        'rechazada' => 'danger',
                                        'pendiente' => 'warning',
                                        default => 'secondary'
                                    };
                                    ?>
                                    <span class="badge bg-<?php echo $badgeClass; ?> rounded-pill px-3 py-2">
                                        <?php echo ucfirst($estado); ?>
                                    </span>
                                </p>
                                <?php if (!empty($receta['firma_electronica']) && empty($receta['doctor_nombre'])): ?>
                                    <p><strong>Firma electrónica:</strong> <?php echo htmlspecialchars($receta['firma_electronica']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($es_menor && !empty($receta['responsable_nombre']) && !empty($receta['responsable_telefono'])): ?>
                        <div class="p-3 bg-warning-subtle rounded-4 mb-4">
                            <h5 class="text-warning"><i class="fas fa-user-shield me-2"></i>Responsable</h5>
                            <hr>
                            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($receta['responsable_nombre']); ?></p>
                            <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($receta['responsable_telefono']); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($receta['contacto_alternativo_nombre']) && !empty($receta['contacto_alternativo_telefono'])): ?>
                        <div class="p-3 bg-danger-subtle rounded-4 mb-4">
                            <h5 class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Contacto alternativo</h5>
                            <hr>
                            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($receta['contacto_alternativo_nombre']); ?></p>
                            <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($receta['contacto_alternativo_telefono']); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="p-3 bg-light rounded-4 mb-4">
                        <h5 class="text-danger"><i class="fas fa-pills me-2"></i>Medicamentos Recetados</h5>
                        <hr>
                        <?php if ($detalles->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Medicamento</th>
                                            <th>Cantidad</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($det = $detalles->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($det['medicamento_nombre']); ?></td>
                                                <td><?php echo $det['cantidad']; ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No hay medicamentos asociados a esta receta.</p>
                        <?php endif; ?>
                    </div>

                    <div class="text-center">
                        <a href="dashboard.php" class="btn btn-outline-secondary rounded-pill px-4">
                            <i class="fas fa-arrow-left me-1"></i> Volver al dashboard
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .glass-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.3); }
    .fade-in { animation: fadeUp 0.5s ease; }
    @keyframes fadeUp { from { opacity:0; transform: translateY(20px); } to { opacity:1; transform: translateY(0); } }
    .bg-warning-subtle { background: #fff3cd; color: #856404; }
    .bg-danger-subtle { background: #f8d7da; color: #721c24; }
</style>

<?php require_once '../../includes/footer.php'; ?>