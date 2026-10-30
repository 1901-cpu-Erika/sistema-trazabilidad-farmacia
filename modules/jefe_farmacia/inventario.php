<?php
if (session_status() === PHP_SESSION_NONE) session_save_path('/tmp'); // Clave para Vercel
    session_start();
require_once __DIR__ . '/../../config/database.php';
if (!isset($conn) || $conn === null) {
    die('Error: No se pudo establecer la conexión a la base de datos. Verifica el archivo config/database.php');
}

if ($_SESSION['tipo_usuario'] != 'jefe_farmacia') {
    header("Location: /login.php");
    exit();
}
$titulo_pagina = "Dashboard de Bodega";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
$total_lotes = 0;
$total_unidades = 0;
$por_vencer = 0;
$stock_critico = 0;
$result = $conn->query("SELECT COUNT(*) as total FROM lotes");
if ($result) {
    $total_lotes = $result->fetch_assoc()['total'] ?? 0;
}
$result = $conn->query("SELECT SUM(cantidad) as total FROM lotes");
if ($result) {
    $total_unidades = $result->fetch_assoc()['total'] ?? 0;
}
$result = $conn->query("SELECT COUNT(*) as total FROM lotes WHERE fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
if ($result) {
    $por_vencer = $result->fetch_assoc()['total'] ?? 0;
}
$result = $conn->query("SELECT COUNT(*) as total FROM lotes WHERE cantidad < 10");
if ($result) {
    $stock_critico = $result->fetch_assoc()['total'] ?? 0;
}

$stock_medicamentos = $conn->query("
    SELECT m.id, m.nombre, m.codigo_minsa, 
           COALESCE(SUM(l.cantidad), 0) as stock_total,
           COUNT(l.id) as num_lotes
    FROM medicamentos m
    LEFT JOIN lotes l ON m.id = l.medicamento_id
    GROUP BY m.id
    HAVING stock_total > 0
    ORDER BY stock_total ASC
    LIMIT 10
");
$alertas = $conn->query("
    SELECT l.*, m.nombre as medicamento_nombre 
    FROM lotes l 
    JOIN medicamentos m ON l.medicamento_id = m.id 
    WHERE l.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY l.fecha_vencimiento ASC
    LIMIT 5
");
?>

<div class="container-fluid px-4 fade-in">

    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden" style="background: linear-gradient(135deg, #1a3a5c 0%, #2d6a9f 100%);">
                <div class="card-body p-4 text-white">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-white bg-opacity-20 p-3 me-3">
                            <i class="fas fa-warehouse fa-2x text-white"></i>
                        </div>
                        <div>
                            <h2 class="display-6 fw-bold mb-1">Bodega / Inventario</h2>
                            <p class="opacity-75 mb-0">Resumen general del inventario de medicamentos</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php if ($alertas && $alertas->num_rows > 0): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                <div>
                    <strong>¡Atención! Medicamentos por vencer en los próximos 7 días:</strong>
                    <ul class="mb-0 mt-2">
                        <?php while ($alerta = $alertas->fetch_assoc()): ?>
                            <li>
                                <strong><?php echo htmlspecialchars($alerta['medicamento_nombre']); ?></strong>
                                – Vence el <?php echo date('d/m/Y', strtotime($alerta['fecha_vencimiento'])); ?>
                                (Stock: <?php echo $alerta['cantidad']; ?> unidades)
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 stat-card">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                        <i class="fas fa-boxes text-primary fa-2x"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0"><?php echo $total_lotes; ?></h5>
                        <small class="text-muted">Total de lotes</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 stat-card">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                        <i class="fas fa-capsules text-success fa-2x"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0"><?php echo number_format($total_unidades); ?></h5>
                        <small class="text-muted">Unidades totales</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 stat-card">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                        <i class="fas fa-clock text-warning fa-2x"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0"><?php echo $por_vencer; ?></h5>
                        <small class="text-muted">Por vencer (30 días)</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 stat-card">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3">
                        <i class="fas fa-exclamation-circle text-danger fa-2x"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0"><?php echo $stock_critico; ?></h5>
                        <small class="text-muted">Stock crítico (&lt; 10)</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-lg rounded-4 glass-card">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h6 class="fw-bold text-primary mb-0">
                        <i class="fas fa-list me-2"></i>Stock por Medicamento (10 con menos stock)
                    </h6>
                </div>
                <div class="card-body p-4 pt-2">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-light rounded-4">
                                <tr>
                                    <th>ID</th>
                                    <th>Medicamento</th>
                                    <th>Código MINSA</th>
                                    <th>Stock Total</th>
                                    <th>Lotes</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($stock_medicamentos && $stock_medicamentos->num_rows > 0): ?>
                                    <?php while ($row = $stock_medicamentos->fetch_assoc()): 
                                        $badge = $row['stock_total'] < 10 ? 'danger' : ($row['stock_total'] < 50 ? 'warning' : 'success');
                                    ?>
                                        <tr>
                                            <td><span class="badge bg-secondary-subtle text-secondary rounded-pill">#<?php echo $row['id']; ?></span></td>
                                            <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                                            <td><?php echo $row['codigo_minsa'] ?: '<span class="text-muted">N/A</span>'; ?></td>
                                            <td><strong><?php echo $row['stock_total']; ?></strong></td>
                                            <td><?php echo $row['num_lotes']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $badge; ?>-subtle text-<?php echo $badge; ?>-emphasis rounded-pill px-3 py-2">
                                                    <?php echo $row['stock_total'] < 10 ? 'Crítico' : ($row['stock_total'] < 50 ? 'Bajo' : 'OK'); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center py-4 text-muted">No hay medicamentos con stock registrado.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 text-center">
                        <a href="lotes.php" class="btn btn-primary rounded-pill px-4">
                            <i class="fas fa-arrow-right me-1"></i> Ver gestión de lotes
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
    .glass-card {
        background: rgba(255,255,255,0.95);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255,255,255,0.3);
    }
    .stat-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 30px rgba(0,0,0,0.08) !important;
    }
    .bg-primary-subtle { background: #d1ecf1 !important; color: #0c5460 !important; }
    .bg-success-subtle { background: #d4edda !important; color: #155724 !important; }
    .bg-warning-subtle { background: #fff3cd !important; color: #856404 !important; }
    .bg-danger-subtle { background: #f8d7da !important; color: #721c24 !important; }
    .bg-secondary-subtle { background: #e2e3e5 !important; color: #383d41 !important; }
    .fade-in { animation: fadeUp 0.6s ease forwards; }
    @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>