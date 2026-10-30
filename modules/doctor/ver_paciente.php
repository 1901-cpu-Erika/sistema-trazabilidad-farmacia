<?php
if (session_status() === PHP_SESSION_NONE) {
    session_save_path('/tmp'); // Clave para Vercel
    session_start();
}
require_once '../../config/database.php';
$titulo_pagina = "Detalle del Paciente";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: buscar_paciente.php");
    exit();
}

$paciente_id = intval($_GET['id']);
$sql = "SELECT * FROM pacientes WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $paciente_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-danger">Paciente no encontrado.</div>';
    require_once '../../includes/footer.php';
    exit();
}

$paciente = $result->fetch_assoc();
$edad = intval($paciente['edad']);
$es_menor = ($edad < 18); // ✅ Calculado con edad

$enfermedades = [];
$sql_enf = "SELECT ec.nombre FROM paciente_enfermedades pe JOIN enfermedades_cronicas ec ON pe.enfermedad_id = ec.id WHERE pe.paciente_id = ?";
$stmt_enf = $conn->prepare($sql_enf);
$stmt_enf->bind_param("i", $paciente_id);
$stmt_enf->execute();
$res_enf = $stmt_enf->get_result();
while ($row = $res_enf->fetch_assoc()) {
    $enfermedades[] = $row['nombre'];
}
$stmt_enf->close();
$sql_recetas = "SELECT id, fecha_receta, estado FROM recetas WHERE paciente_id = ? ORDER BY fecha_receta DESC";
$stmt_rec = $conn->prepare($sql_recetas);
$stmt_rec->bind_param("i", $paciente_id);
$stmt_rec->execute();
$recetas = $stmt_rec->get_result();
?>

<div class="container-fluid fade-in">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card border-0 shadow-lg rounded-4 glass-card">
                <div class="card-header bg-primary text-white rounded-top-4 py-3">
                    <h4 class="mb-0"><i class="fas fa-user-circle me-2"></i> Detalles del Paciente</h4>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-4">
                                <h5 class="text-primary"><i class="fas fa-id-card me-2"></i>Información Personal</h5>
                                <hr>
                                <p><strong>Nombre completo:</strong> <?php echo htmlspecialchars($paciente['nombre'] . ' ' . $paciente['apellido']); ?></p>
                                <p><strong>Cédula:</strong> <?php echo !empty($paciente['cedula']) ? htmlspecialchars($paciente['cedula']) : 'No registrada'; ?></p>
                                <p><strong>Fecha de nacimiento:</strong> <?php echo date('d/m/Y', strtotime($paciente['fecha_nacimiento'])); ?></p>
                                <p><strong>Edad:</strong> <?php echo $edad; ?> años</p>
                                <p><strong>Sexo:</strong> <?php echo $paciente['sexo'] ?: 'No especificado'; ?></p>
                                <p><strong>Tipo:</strong> 
                                    <?php if ($es_menor): ?>
                                        <span class="badge bg-warning">Menor de edad</span>
                                        <?php if (!empty($paciente['nombre_tutor'])): ?>
                                            <br><strong>Tutor:</strong> <?php echo htmlspecialchars($paciente['nombre_tutor']); ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-success">Adulto</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-4">
                                <h5 class="text-success"><i class="fas fa-phone-alt me-2"></i>Contacto</h5>
                                <hr>
                                <p><strong>Teléfono:</strong> <?php echo !empty($paciente['telefono']) ? htmlspecialchars($paciente['telefono']) : 'No registrado'; ?></p>
                                <p><strong>WhatsApp:</strong> <?php echo ($paciente['tiene_whatsapp'] ?? 0) ? '<i class="fas fa-check-circle text-success"></i> Sí' : '<i class="fas fa-times-circle text-danger"></i> No'; ?></p>
                                <?php if (!empty($paciente['responsable_nombre'])): ?>
                                    <hr>
                                    <h6 class="text-warning">Responsable</h6>
                                    <p><strong>Nombre:</strong> <?php echo htmlspecialchars($paciente['responsable_nombre']); ?></p>
                                    <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($paciente['responsable_telefono']); ?></p>
                                    <p><strong>Cédula:</strong> <?php echo htmlspecialchars($paciente['responsable_cedula']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($paciente['contacto_alternativo_nombre'])): ?>
                                    <hr>
                                    <h6 class="text-danger">Contacto alternativo</h6>
                                    <p><strong>Nombre:</strong> <?php echo htmlspecialchars($paciente['contacto_alternativo_nombre']); ?></p>
                                    <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($paciente['contacto_alternativo_telefono']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="p-3 bg-light rounded-4">
                                <h5 class="text-danger"><i class="fas fa-heartbeat me-2"></i>Enfermedades Crónicas</h5>
                                <hr>
                                <?php if (!empty($enfermedades)): ?>
                                    <?php foreach ($enfermedades as $e): ?>
                                        <span class="badge bg-danger rounded-pill px-3 py-2 me-1"><?php echo htmlspecialchars($e); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-success"><i class="fas fa-check-circle me-1"></i> No presenta enfermedades crónicas.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="p-3 bg-light rounded-4">
                                <h5 class="text-primary"><i class="fas fa-prescription me-2"></i>Historial de Recetas</h5>
                                <hr>
                                <?php if ($recetas->num_rows > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Fecha</th>
                                                    <th>Estado</th>
                                                    <th>Acción</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($rec = $recetas->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo $rec['id']; ?></td>
                                                        <td><?php echo date('d/m/Y', strtotime($rec['fecha_receta'])); ?></td>
                                                        <td><span class="badge bg-<?php echo $rec['estado'] == 'entregada' ? 'success' : ($rec['estado'] == 'aprobada' ? 'primary' : 'danger'); ?>"><?php echo ucfirst($rec['estado']); ?></span></td>
                                                        <td><a href="detalle_receta.php?id=<?php echo $rec['id']; ?>" class="btn btn-sm btn-outline-primary">Ver</a></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">Este paciente no tiene recetas registradas.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 text-center">
                        <a href="crear_receta.php?paciente_id=<?php echo $paciente_id; ?>" class="btn btn-primary rounded-pill px-4">
                            <i class="fas fa-prescription me-1"></i> Nueva Receta
                        </a>
                        <a href="buscar_paciente.php" class="btn btn-outline-secondary rounded-pill px-4">
                            <i class="fas fa-arrow-left me-1"></i> Volver
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
</style>

<?php require_once '../../includes/footer.php'; ?>