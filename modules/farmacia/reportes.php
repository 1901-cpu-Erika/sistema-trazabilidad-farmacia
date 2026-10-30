<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/database.php';
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] != 'farmacia') {
    header("Location: /login.php");
    exit();
}

$titulo_pagina = "Reportes y Estadísticas";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin    = $_GET['fecha_fin']    ?? date('Y-m-d');

$sql_top_medicamentos = "SELECT m.nombre, SUM(rd.cantidad) as total_recetado
                         FROM receta_detalles rd
                         JOIN medicamentos m ON rd.medicamento_id = m.id
                         JOIN recetas r ON rd.receta_id = r.id
                         WHERE r.fecha_receta BETWEEN ? AND ?
                         GROUP BY m.id, m.nombre
                         ORDER BY total_recetado DESC
                         LIMIT 10";
$stmt = $conn->prepare($sql_top_medicamentos);
$stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
$stmt->execute();
$top_medicamentos = $stmt->get_result();

$sql_recetas_estado = "SELECT estado, COUNT(*) as total
                       FROM recetas
                       WHERE fecha_receta >= ? AND fecha_receta < DATE_ADD(?, INTERVAL 1 DAY)
                       GROUP BY estado";
$stmt = $conn->prepare($sql_recetas_estado);
$stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
$stmt->execute();
$recetas_estado = $stmt->get_result();

$sql_top_doctores = "SELECT u.nombre, COUNT(r.id) AS total_recetas
                     FROM recetas r
                     JOIN usuarios u ON r.doctor_id = u.id
                     WHERE r.fecha_receta >= ? AND r.fecha_receta < DATE_ADD(?, INTERVAL 1 DAY)
                     GROUP BY u.id, u.nombre
                     ORDER BY total_recetas DESC
                     LIMIT 5";
$stmt = $conn->prepare($sql_top_doctores);
$stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
$stmt->execute();
$top_doctores = $stmt->get_result();

$sql_ventas_mensuales = "SELECT DATE_FORMAT(fecha_receta, '%Y-%m') as mes, COUNT(*) as total_recetas
                         FROM recetas
                         WHERE fecha_receta >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                         GROUP BY DATE_FORMAT(fecha_receta, '%Y-%m')
                         ORDER BY mes ASC";
$ventas_mensuales = $conn->query($sql_ventas_mensuales);

$sql_por_vencer = "SELECT m.nombre, l.cantidad, l.fecha_vencimiento, l.codigo_qr
                   FROM lotes l
                   JOIN medicamentos m ON l.medicamento_id = m.id
                   WHERE l.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                   ORDER BY l.fecha_vencimiento ASC";
$por_vencer = $conn->query($sql_por_vencer);

$sql_stock_total = "SELECT m.nombre, SUM(l.cantidad) as stock_total
                    FROM medicamentos m
                    LEFT JOIN lotes l ON m.id = l.medicamento_id
                    GROUP BY m.id, m.nombre
                    HAVING stock_total > 0
                    ORDER BY stock_total ASC";
$stock_total = $conn->query($sql_stock_total);

$medicamentos_labels = []; $medicamentos_data = [];
while ($row = $top_medicamentos->fetch_assoc()) {
    $medicamentos_labels[] = $row['nombre'];
    $medicamentos_data[]   = (int)$row['total_recetado'];
}

$estados_labels = []; $estados_data = [];
while ($row = $recetas_estado->fetch_assoc()) {
    $estados_labels[] = ucfirst($row['estado']);
    $estados_data[]   = (int)$row['total'];
}

$doctores_labels = []; $doctores_data = [];
while ($row = $top_doctores->fetch_assoc()) {
    $doctores_labels[] = $row['nombre'];
    $doctores_data[]   = (int)$row['total_recetas'];
}

$meses_labels = []; $meses_data = [];
while ($row = $ventas_mensuales->fetch_assoc()) {
    $meses_labels[] = $row['mes'];
    $meses_data[]   = (int)$row['total_recetas'];
}
?>

<style>
    :root {
        --rep-primary: #7c3aed;
        --rep-primary-dark: #4c1d95;
    }
    .main-content { padding-top: 0 !important; background: #f4f6f9; }
    .container-fluid.px-4 { margin-top: -15px; max-width: 1400px; margin-left: auto; margin-right: auto; }

    .rep-hero {
        background: linear-gradient(135deg, #1e1b4b 0%, #4c1d95 50%, #7c3aed 100%);
        border-radius: 24px;
        padding: 32px 36px;
        color: #fff;
        position: relative;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(76, 29, 149, 0.3);
        margin-bottom: 30px;
    }
    .rep-hero::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background-image: radial-gradient(circle, rgba(255,255,255,0.15) 1px, transparent 1px);
        background-size: 25px 25px;
        opacity: 0.5;
    }
    .rep-hero::after {
        content: '\f201';
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        position: absolute;
        right: -20px; bottom: -40px;
        font-size: 220px;
        opacity: 0.05;
        transform: rotate(-10deg);
    }
    .rep-hero-content { position: relative; z-index: 1; }
    .rep-hero-icon {
        width: 68px; height: 68px;
        background: rgba(255,255,255,0.15);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.7rem;
        flex-shrink: 0;
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    }
    .rep-hero h2 { font-size: 1.8rem; font-weight: 800; margin: 0 0 6px 0; letter-spacing: -0.5px; }
    .rep-hero p { margin: 0; opacity: 0.85; font-size: 0.95rem; }

    .btn-export {
        padding: 10px 20px;
        border-radius: 50px;
        font-weight: 700;
        font-size: 0.85rem;
        transition: all 0.3s;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }
    .btn-export-excel { background: linear-gradient(135deg, #059669, #10b981); color: white; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); }
    .btn-export-excel:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(16, 185, 129, 0.5); color: white; }
    .btn-export-pdf { background: linear-gradient(135deg, #dc2626, #ef4444); color: white; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3); }
    .btn-export-pdf:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(239, 68, 68, 0.5); color: white; }

    .kpi-alert-card {
        background: #fff;
        border-radius: 20px;
        padding: 24px;
        border: none;
        box-shadow: 0 8px 30px rgba(0,0,0,0.05);
        cursor: pointer;
        transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        gap: 18px;
    }
    .kpi-alert-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; width: 6px; height: 100%;
        background: var(--kpi-color);
        transition: width 0.3s;
    }
    .kpi-alert-card:hover { transform: translateY(-6px); box-shadow: 0 16px 40px rgba(0,0,0,0.1); }
    .kpi-alert-card:hover::before { width: 10px; }
    
    .kpi-icon-wrapper {
        width: 60px; height: 60px;
        border-radius: 16px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.6rem; color: white;
        flex-shrink: 0;
        box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    }
    
    .glass-card {
        border: 1px solid rgba(255, 255, 255, 0.4);
        border-radius: 20px;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        box-shadow: 0 8px 32px rgba(31, 38, 135, 0.07);
        overflow: hidden;
        height: 100%;
        transition: transform 0.3s ease;
    }
    .glass-card:hover { transform: translateY(-5px); }
    
    .glass-card .card-header {
        background: transparent;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        padding: 20px 24px;
    }
    .glass-card .card-header h6 {
        font-size: 1rem; margin: 0; font-weight: 800; color: #1e293b;
        display: flex; align-items: center; gap: 10px;
    }
    .glass-card .card-header h6 i {
        width: 32px; height: 32px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        background: rgba(124, 58, 237, 0.1); color: var(--rep-primary);
        font-size: 0.9rem;
    }

    .chart-box {
        position: relative;
        width: 100%;
        height: 280px;   
    }
    .chart-box canvas {
        max-width: 100% !important;
        max-height: 100% !important;
    }

    .modal-premium .modal-content {
        border: none;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 25px 80px rgba(0,0,0,0.4);
        background: #f8fafc;
    }
    .modal-premium .modal-header {
        background: linear-gradient(135deg, var(--rep-primary-dark), var(--rep-primary));
        padding: 24px 30px;
        border: none;
        position: relative;
    }
    .modal-premium .modal-header h5 {
        color: #fff; font-weight: 800; font-size: 1.2rem; margin: 0;
        display: flex; align-items: center; gap: 12px;
    }
    .modal-premium .modal-body { padding: 0; }
    .modal-premium .table { margin: 0; }
    .modal-premium .table thead th {
        background: #fff; color: #64748b; font-weight: 700; font-size: 0.75rem;
        text-transform: uppercase; letter-spacing: 0.5px; padding: 16px 24px;
        border-bottom: 2px solid #e2e8f0; position: sticky; top: 0; z-index: 10;
    }
    .modal-premium .table tbody td {
        padding: 16px 24px; vertical-align: middle; background: #fff;
        border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; font-weight: 500;
    }
    .modal-premium .table tbody tr:hover td { background: #f8fafc; }

    .fade-in { animation: fadeUp 0.6s ease forwards; }
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(20px); }
        to   { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="container-fluid px-4 fade-in">
    
    <div class="rep-hero">
        <div class="rep-hero-content d-flex align-items-center justify-content-between flex-wrap gap-4">
            <div class="d-flex align-items-center">
                <div class="rep-hero-icon me-4">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div>
                    <h2>Reportes y Estadísticas</h2>
                    <p><i class="fas fa-chart-line me-2"></i>Análisis de rendimiento y medicamentos</p>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn-export btn-export-excel" onclick="exportarExcel()">
                    <i class="fas fa-file-excel"></i> Excel
                </button>
                <button class="btn-export btn-export-pdf" onclick="exportarPDF()">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4 col-md-6">
            <div class="kpi-alert-card" data-bs-toggle="modal" data-bs-target="#modalPorVencer" style="--kpi-color: #f59e0b;">
                <div class="kpi-icon-wrapper" style="background: linear-gradient(135deg, #d97706, #f59e0b);">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <span class="text-muted fw-bold text-uppercase" style="font-size: 0.75rem; letter-spacing: 1px;">Alerta de Vencimiento</span>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo $por_vencer->num_rows; ?> <span style="font-size: 1rem; font-weight: 600;">Lotes</span></h3>
                    <small class="text-muted"><i class="fas fa-search-plus me-1"></i> Clic para ver detalles</small>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="kpi-alert-card" data-bs-toggle="modal" data-bs-target="#modalStockBajo" style="--kpi-color: #ef4444;">
                <div class="kpi-icon-wrapper" style="background: linear-gradient(135deg, #b91c1c, #ef4444);">
                    <i class="fas fa-box-open"></i>
                </div>
                <div>
                    <span class="text-muted fw-bold text-uppercase" style="font-size: 0.75rem; letter-spacing: 1px;">Alerta de Stock</span>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo $stock_total->num_rows; ?> <span style="font-size: 1rem; font-weight: 600;">Medicamentos</span></h3>
                    <small class="text-muted"><i class="fas fa-search-plus me-1"></i> Clic para ver detalles</small>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="kpi-alert-card" style="--kpi-color: #10b981; cursor: default;">
                <div class="kpi-icon-wrapper" style="background: linear-gradient(135deg, #047857, #10b981);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <span class="text-muted fw-bold text-uppercase" style="font-size: 0.75rem; letter-spacing: 1px;">Estado del Sistema</span>
                    <h3 class="fw-bold mb-0 text-dark">Óptimo</h3>
                    <small class="text-muted">Generado: <?php echo date('d/m/Y'); ?></small>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow glass-card mb-4 border-0">
        <div class="card-body py-3 px-4">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted">Fecha Inicio</label>
                    <input type="date" name="fecha_inicio" class="form-control form-control-sm bg-light border-0" style="border-radius: 10px;"
                           value="<?php echo htmlspecialchars($fecha_inicio); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted">Fecha Fin</label>
                    <input type="date" name="fecha_fin" class="form-control form-control-sm bg-light border-0" style="border-radius: 10px;"
                           value="<?php echo htmlspecialchars($fecha_fin); ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-sm w-100 text-white fw-bold" style="background: linear-gradient(135deg,#4c1d95,#7c3aed); border-radius: 10px; padding: 8px;">
                        <i class="fas fa-filter me-2"></i> Filtrar Resultados
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <div class="card glass-card mb-4">
                <div class="card-header">
                    <h6><i class="fas fa-chart-bar"></i> Top 10 Medicamentos más Recetados</h6>
                </div>
                <div class="card-body">
                    <div class="chart-box"><canvas id="chartMedicamentos"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card glass-card mb-4">
                <div class="card-header">
                    <h6><i class="fas fa-chart-pie"></i> Distribución de Recetas por Estado</h6>
                </div>
                <div class="card-body">
                    <div class="chart-box"><canvas id="chartEstado"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card glass-card mb-4">
                <div class="card-header">
                    <h6><i class="fas fa-user-md"></i> Top 5 Doctores con más Recetas</h6>
                </div>
                <div class="card-body">
                    <div class="chart-box"><canvas id="chartDoctores"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card glass-card mb-4">
                <div class="card-header">
                    <h6><i class="fas fa-chart-line"></i> Recetas por Mes (Últimos 12 meses)</h6>
                </div>
                <div class="card-body">
                    <div class="chart-box"><canvas id="chartVentasMensuales"></canvas></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade modal-premium" id="modalPorVencer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #b45309, #f59e0b);">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Medicamentos por Vencer (30 días)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Medicamento</th>
                            <th>Stock</th>
                            <th>Vencimiento</th>
                            <th>Código QR</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($por_vencer->num_rows > 0): ?>
                            <?php $por_vencer->data_seek(0); while ($med = $por_vencer->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><i class="fas fa-capsules text-warning me-2"></i><?php echo htmlspecialchars($med['nombre']); ?></td>
                                    <td><span class="badge bg-warning text-dark px-3 py-2 rounded-pill"><?php echo (int)$med['cantidad']; ?></span></td>
                                    <td class="text-danger fw-bold"><?php echo date('d/m/Y', strtotime($med['fecha_vencimiento'])); ?></td>
                                    <td><code class="text-muted"><?php echo htmlspecialchars(substr($med['codigo_qr'], 0, 12)) . '...'; ?></code></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center py-5 text-muted">
                                <i class="fas fa-check-circle fa-3x text-success mb-3 d-block opacity-50"></i>
                                No hay medicamentos próximos a vencer
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer border-0 bg-light p-3">
                <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade modal-premium" id="modalStockBajo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #991b1b, #ef4444);">
                <h5 class="modal-title"><i class="fas fa-box-open"></i> Alerta de Stock (Menor a Mayor)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Medicamento</th>
                            <th>Stock Total</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($stock_total->num_rows > 0): ?>
                            <?php $stock_total->data_seek(0); while ($med = $stock_total->fetch_assoc()):
                                $stock = (int)$med['stock_total'];
                                $badge = $stock < 10 ? 'danger' : ($stock < 50 ? 'warning' : 'success');
                            ?>
                                <tr>
                                    <td class="fw-bold text-dark"><i class="fas fa-pills text-primary me-2"></i><?php echo htmlspecialchars($med['nombre']); ?></td>
                                    <td><h5 class="mb-0 fw-bold"><?php echo $stock; ?> <small class="text-muted fs-6">uds</small></h5></td>
                                    <td>
                                        <span class="badge bg-<?php echo $badge; ?> px-3 py-2 rounded-pill shadow-sm">
                                            <?php echo $stock < 10 ? 'Crítico' : ($stock < 50 ? 'Bajo' : 'Óptimo'); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center py-5 text-muted">
                                <i class="fas fa-check-circle fa-3x text-success mb-3 d-block opacity-50"></i>
                                No hay datos de stock registrados
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer border-0 bg-light p-3">
                <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const PALETA = ['#7c3aed','#a78bfa','#3b82f6','#10b981','#f59e0b','#ef4444','#06b6d4','#8b5cf6','#ec4899','#22c55e'];
    const sinDatos = (id) => {
        const c = document.getElementById(id);
        if (c) c.parentElement.innerHTML =
            '<div class="text-center text-muted py-5"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>Sin datos en este rango</div>';
    };
    
    const medsData = <?php echo json_encode($medicamentos_data); ?>;
    if (medsData.length) {
        new Chart(document.getElementById('chartMedicamentos'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($medicamentos_labels); ?>,
                datasets: [{
                    label: 'Unidades Recetadas',
                    data: medsData,
                    backgroundColor: PALETA,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } }
            }
        });
    } else sinDatos('chartMedicamentos');

    const estadosData = <?php echo json_encode($estados_data); ?>;
    if (estadosData.length) {
        new Chart(document.getElementById('chartEstado'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($estados_labels); ?>,
                datasets: [{
                    data: estadosData,
                    backgroundColor: ['#10b981','#f59e0b','#ef4444','#3b82f6','#7c3aed'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                cutout: '75%',
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, font: { size: 12, weight: 'bold' } } } }
            }
        });
    } else sinDatos('chartEstado');

    const docData = <?php echo json_encode($doctores_data); ?>;
    if (docData.length) {
        new Chart(document.getElementById('chartDoctores'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($doctores_labels); ?>,
                datasets: [{
                    label: 'Recetas',
                    data: docData,
                    backgroundColor: '#3b82f6',
                    borderRadius: 8
                }]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } }, y: { grid: { display: false } } }
            }
        });
    } else sinDatos('chartDoctores');

    const mesData = <?php echo json_encode($meses_data); ?>;
    if (mesData.length) {
        new Chart(document.getElementById('chartVentasMensuales'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($meses_labels); ?>,
                datasets: [{
                    label: 'Recetas',
                    data: mesData,
                    borderColor: '#7c3aed',
                    backgroundColor: 'rgba(124,58,237,0.1)',
                    fill: true, tension: 0.4,
                    pointBackgroundColor: '#7c3aed', pointRadius: 5, pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } }
            }
        });
    } else sinDatos('chartVentasMensuales');
});

function exportarExcel() {
    Swal.fire({
        title: 'Exportar a Excel',
        text: 'Se generará un archivo Excel con los datos del reporte',
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Descargar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#10b981',
    }).then((r) => {
        if (r.isConfirmed) {
            window.location.href = 'exportar_excel.php?fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>';
        }
    });
}

function exportarPDF() {
    Swal.fire({
        title: 'Exportar a PDF',
        text: 'Se generará un archivo PDF con los reportes',
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Descargar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ef4444',
    }).then((r) => {
        if (r.isConfirmed) {
            window.location.href = 'exportar_pdf.php?fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>';
        }
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>