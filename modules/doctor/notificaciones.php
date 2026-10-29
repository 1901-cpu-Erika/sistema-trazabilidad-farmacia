<?php
require_once '../../config/database.php';
$titulo_pagina = "Notificaciones";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] != 'doctor') {
    header("Location: ../../login.php");
    exit();
}

$doctor_id = $_SESSION['usuario_id'];


$sql = "SELECT n.*, 
               CONCAT(p.nombre, ' ', p.apellido) AS paciente_nombre,
               u2.nombre AS admin_nombre,
               r.id AS receta_id
        FROM notificaciones n
        LEFT JOIN recetas r ON n.receta_id = r.id
        LEFT JOIN pacientes p ON r.paciente_id = p.id
        LEFT JOIN usuarios u2 ON u2.id = n.admin_id
        WHERE n.doctor_id = ?
        ORDER BY n.fecha DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$notificaciones = $stmt->get_result();
?>

<div class="container-fluid px-4 fade-in">

    <!-- HERO -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden" style="background: linear-gradient(135deg, #1a3a5c 0%, #2d6a9f 100%);">
                <div class="card-body p-4 text-white">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-white bg-opacity-25 p-3 me-3">
                            <i class="fas fa-bell fa-2x text-white"></i>
                        </div>
                        <div>
                            <h2 class="display-6 fw-bold mb-1">Notificaciones</h2>
                            <p class="opacity-75 mb-0">Todas las notificaciones de tus recetas</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- LISTA -->
    <div class="card border-0 shadow-lg rounded-4 glass-card">
        <div class="card-header bg-transparent border-0 pt-4 px-4">
            <h6 class="fw-bold text-primary mb-0">
                <i class="fas fa-list me-2"></i>Historial de Notificaciones
            </h6>
        </div>
        <div class="card-body p-4 pt-2">
            <?php if ($notificaciones && $notificaciones->num_rows > 0): ?>
                <div class="list-group list-group-flush">
                    <?php while ($notif = $notificaciones->fetch_assoc()): 
                        $icono = match($notif['tipo'] ?? '') {
                            'aprobada' => 'check-circle',
                            'rechazada' => 'times-circle',
                            'entregada' => 'truck',
                            'pendiente' => 'clock',
                            default => 'info-circle'
                        };
                        $color = match($notif['tipo'] ?? '') {
                            'aprobada' => '#10b981',
                            'rechazada' => '#ef4444',
                            'entregada' => '#06b6d4',
                            default => '#3b82f6'
                        };
                    ?>
                        <div class="list-group-item border-0 d-flex align-items-start gap-3 py-3">
                            <span style="font-size: 22px; color: <?php echo $color; ?>;">
                                <i class="fas fa-<?php echo $icono; ?>"></i>
                            </span>
                            <div class="flex-grow-1">
                                <p class="mb-1 fw-semibold"><?php echo htmlspecialchars($notif['mensaje'] ?? ''); ?></p>
                                <small class="text-muted">
                                    <i class="fas fa-user-md me-1"></i>Admin: <?php echo htmlspecialchars($notif['admin_nombre'] ?? 'Farmacia'); ?>
                                    <span class="mx-2">|</span>
                                    <i class="fas fa-user me-1"></i>Paciente: <?php echo htmlspecialchars($notif['paciente_nombre'] ?? 'N/A'); ?>
                                    <span class="mx-2">|</span>
                                    <i class="fas fa-calendar-alt me-1"></i><?php echo date('d/m/Y H:i', strtotime($notif['fecha'] ?? 'now')); ?>
                                </small>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-bell-slash fa-3x mb-3 d-block"></i>
                    <h5>No hay notificaciones disponibles</h5>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .glass-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.3); }
    .fade-in { animation: fadeUp 0.6s ease forwards; }
    @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>

<?php require_once '../../includes/footer.php'; ?>