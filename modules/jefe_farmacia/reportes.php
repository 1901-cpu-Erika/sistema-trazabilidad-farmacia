<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] != 'jefe_farmacia') {
    header("Location: ../../login.php");
    exit();
}
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin    = $_GET['fecha_fin']    ?? date('Y-m-d');

$sql_estado = "SELECT estado, COUNT(*) AS total FROM recetas WHERE fecha_receta BETWEEN ? AND ? GROUP BY estado";
$stmt = $conn->prepare($sql_estado);
$stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
$stmt->execute();
$res_estado = $stmt->get_result();
$estados_labels = [];
$estados_data   = [];
while ($row = $res_estado->fetch_assoc()) {
    $estados_labels[] = ucfirst($row['estado']);
    $estados_data[]   = (int)$row['total'];
}
$stmt->close();

$sql_top_meds = "SELECT m.nombre, SUM(rd.cantidad) AS total FROM receta_detalles rd JOIN medicamentos m ON rd.medicamento_id = m.id JOIN recetas r ON rd.receta_id = r.id WHERE r.fecha_receta BETWEEN ? AND ? GROUP BY m.id, m.nombre ORDER BY total DESC LIMIT 10";
$stmt = $conn->prepare($sql_top_meds);
$stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
$stmt->execute();
$res_meds = $stmt->get_result();
$meds_labels = [];
$meds_data   = [];
while ($row = $res_meds->fetch_assoc()) {
    $meds_labels[] = $row['nombre'];
    $meds_data[]   = (int)$row['total'];
}
$stmt->close();

$sql_top_doc = "SELECT u.nombre, COUNT(r.id) AS total FROM recetas r JOIN usuarios u ON r.doctor_id = u.id WHERE r.fecha_receta BETWEEN ? AND ? GROUP BY u.id, u.nombre ORDER BY total DESC LIMIT 5";
$stmt = $conn->prepare($sql_top_doc);
$stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
$stmt->execute();
$res_doc = $stmt->get_result();
$doc_labels = [];
$doc_data   = [];
while ($row = $res_doc->fetch_assoc()) {
    $doc_labels[] = $row['nombre'];
    $doc_data[]   = (int)$row['total'];
}
$stmt->close();

$sql_meses = "SELECT DATE_FORMAT(fecha_receta, '%Y-%m') AS mes, COUNT(*) AS total FROM recetas WHERE fecha_receta >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY mes ORDER BY mes ASC";
$res_meses = $conn->query($sql_meses);
$meses_labels = [];
$meses_data   = [];
while ($row = $res_meses->fetch_assoc()) {
    $meses_labels[] = $row['mes'];
    $meses_data[]   = (int)$row['total'];
}

$por_vencer = $conn->query("
    SELECT m.nombre, l.cantidad, l.fecha_vencimiento
    FROM lotes l JOIN medicamentos m ON l.medicamento_id = m.id
    WHERE l.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY l.fecha_vencimiento ASC
");

$stock_critico = $conn->query("
    SELECT m.nombre, COALESCE(SUM(l.cantidad), 0) AS stock
    FROM medicamentos m LEFT JOIN lotes l ON m.id = l.medicamento_id
    GROUP BY m.id, m.nombre HAVING stock < 10 ORDER BY stock ASC
");

$total_por_vencer = $por_vencer ? $por_vencer->num_rows : 0;
$total_stock_critico = $stock_critico ? $stock_critico->num_rows : 0;

$total_recetas = $conn->query("SELECT COUNT(*) t FROM recetas WHERE fecha_receta BETWEEN '$fecha_inicio' AND '$fecha_fin'")->fetch_assoc()['t'];
$total_unidades = $conn->query("SELECT COALESCE(SUM(rd.cantidad),0) t FROM receta_detalles rd JOIN recetas r ON rd.receta_id = r.id WHERE r.fecha_receta BETWEEN '$fecha_inicio' AND '$fecha_fin'")->fetch_assoc()['t'];
$medicamentos_unicos = $conn->query("SELECT COUNT(DISTINCT medicamento_id) t FROM receta_detalles rd JOIN recetas r ON rd.receta_id = r.id WHERE r.fecha_receta BETWEEN '$fecha_inicio' AND '$fecha_fin'")->fetch_assoc()['t'];

$titulo_pagina = "Reportes";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<style>
    :root {
        --rep-primary: #059669;
        --rep-dark: #065f46;
        --rep-light: #d1fae5;
    }
    .rep-hero {
        background: linear-gradient(135deg, var(--rep-dark) 0%, var(--rep-primary) 55%, #10b981 100%);
        border-radius: 22px; padding: 32px 34px; color: #fff;
        position: relative; overflow: hidden;
        box-shadow: 0 15px 40px rgba(5,150,105,0.25); margin-bottom: 26px;
    }
    .rep-hero::before {
        content: '\f080'; font-family: "Font Awesome 6 Free"; font-weight: 900;
        position: absolute; right: 20px; bottom: -40px; font-size: 200px; opacity: 0.08;
    }
    .rep-hero-icon {
        background: rgba(255,255,255,0.2); width: 64px; height: 64px;
        border-radius: 18px; display: flex; align-items: center; justify-content: center;
        font-size: 1.7rem; flex-shrink: 0; backdrop-filter: blur(5px);
    }
    .rep-hero h3 { font-size: 1.7rem; font-weight: 700; letter-spacing: 0.5px; }
    
    /* Botones elegantes */
    .btn-export {
        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        padding: 10px 22px; border-radius: 50rem; font-weight: 700; font-size: 0.9rem;
        border: none; transition: all 0.3s ease; text-decoration: none; color: #fff;
    }
    .btn-excel { background: linear-gradient(135deg, #15803d, #16a34a); box-shadow: 0 4px 15px rgba(22, 163, 74, 0.3); }
    .btn-excel:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(22, 163, 74, 0.5); color: #fff; }
    .btn-pdf { background: linear-gradient(135deg, #b91c1c, #dc2626); box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3); }
    .btn-pdf:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(220, 38, 38, 0.5); color: #fff; }
    .btn-dashboard-glass { background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.3); color: #fff; }
    .btn-dashboard-glass:hover { background: rgba(255, 255, 255, 0.3); transform: translateY(-3px); color: #fff; }

    .rep-filter {
        background: #fff; border-radius: 16px; padding: 20px 24px;
        box-shadow: 0 4px 18px rgba(15,23,42,0.04); border: 1px solid #f1f5f9; margin-bottom: 28px;
    }
    .rep-filter .form-control {
        border-radius: 12px; border: 1px solid #e2e8f0; padding: 10px 16px;
        font-size: 0.9rem; background: #f8fafc; transition: all 0.2s;
    }
    .rep-filter .form-control:focus { border-color: var(--rep-primary); box-shadow: 0 0 0 4px rgba(5,150,105,0.1); background: #fff; }
    
    .rep-btn-primary {
        background: linear-gradient(135deg, var(--rep-dark), var(--rep-primary));
        color: #fff; border: none; border-radius: 12px; padding: 10px 22px; font-weight: 600; transition: all 0.25s;
    }
    .rep-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(5,150,105,0.3); color: #fff; }

    /* --- TARJETAS UNIFICADAS ESTILO DASHBOARD --- */
    .stat-card {
        border: none; border-radius: 18px; color: white;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden; position: relative; height: 100%;
    }
    .stat-card.clickable { cursor: pointer; }
    .stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.15); }
    .stat-card.clickable:hover { filter: brightness(1.05); }
    
    /* Nuevos colores para los KPIs */
    .card-blue   { background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); }
    .card-teal   { background: linear-gradient(135deg, #115e59 0%, #14b8a6 100%); }
    .card-purple { background: linear-gradient(135deg, #4c1d95 0%, #8b5cf6 100%); }
    .card-red    { background: linear-gradient(135deg, #991b1b 0%, #ef4444 100%); }
    .card-green  { background: linear-gradient(135deg, #047857 0%, #10b981 100%); }
    
    /* Ajuste para que el texto no se corte */
    .stat-card .card-body { padding: 1.25rem !important; }
    .stat-card p { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .stat-card h3 { font-size: 1.8rem; }
    
    .stat-icon { position: absolute; right: 10px; bottom: 0px; font-size: 75px; opacity: 0.12; }
    .card-content { position: relative; z-index: 1; }

    /* Contenedores de Gráficos */
    .rep-card {
        background: #fff; border-radius: 20px; border: 1px solid #f1f5f9;
        box-shadow: 0 6px 22px rgba(15,23,42,0.04); overflow: hidden; height: 100%;
        display: flex; flex-direction: column;
    }
    .rep-card-header {
        padding: 20px 24px; display: flex; align-items: center; justify-content: space-between;
        border-bottom: 1px solid #f1f5f9; background: #fff;
    }
    .rep-card-header h6 {
        margin: 0; font-weight: 700; color: #1e293b; font-size: 1rem; display: flex; align-items: center; gap: 10px;
    }
    .rep-card-header h6 i {
        width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
        background: var(--rep-light); color: var(--rep-dark); border-radius: 10px; font-size: 0.95rem;
    }

    /* Tablas dentro de los modales */
    .table-modal th {
        background: #f8fafc; font-size: 0.75rem; text-transform: uppercase; color: #64748b;
        border-bottom: 2px solid #e2e8f0; padding: 16px 20px; font-weight: 700; letter-spacing: 0.5px;
    }
    .table-modal td { padding: 16px 20px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
    .table-modal tr:hover { background: #f8fafc; }

    .chart-box { position: relative; width: 100%; height: 300px; padding: 20px; }
    .chart-box canvas { max-height: 100%; max-width: 100%; }

    .fade-in { animation: fadeUp 0.5s ease forwards; }
    @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

    @media (max-width: 768px) {
        .rep-hero { padding: 22px; }
        .rep-hero h3 { font-size: 1.3rem; }
        .stat-card h3 { font-size: 1.5rem; }
    }
</style>

<div class="container-fluid px-4 fade-in">

    <!-- HERO -->
    <div class="rep-hero d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center">
            <div class="rep-hero-icon me-3"><i class="fas fa-chart-line"></i></div>
            <div>
                <h3 class="mb-1">Reportes y Estadísticas</h3>
                <p class="mb-0 opacity-75" style="font-size: 0.95rem;">Análisis completo de recetas, medicamentos y personal</p>
            </div>
        </div>
        
        <div class="d-flex gap-2 flex-wrap">
            <a href="exportar_excel.php?fecha_inicio=<?php echo urlencode($fecha_inicio); ?>&fecha_fin=<?php echo urlencode($fecha_fin); ?>" class="btn-export btn-excel">
                <i class="fas fa-file-excel"></i> Excel
            </a>
            <a href="exportar_pdf.php?fecha_inicio=<?php echo urlencode($fecha_inicio); ?>&fecha_fin=<?php echo urlencode($fecha_fin); ?>" target="_blank" class="btn-export btn-pdf">
                <i class="fas fa-file-pdf"></i> PDF
            </a>
            <a href="dashboard.php" class="btn-export btn-dashboard-glass">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- FILTRO -->
    <div class="rep-filter">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted text-uppercase" style="letter-spacing: 0.5px;">Fecha Inicio</label>
                <input type="date" name="fecha_inicio" class="form-control" value="<?php echo htmlspecialchars($fecha_inicio); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted text-uppercase" style="letter-spacing: 0.5px;">Fecha Fin</label>
                <input type="date" name="fecha_fin" class="form-control" value="<?php echo htmlspecialchars($fecha_fin); ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="rep-btn-primary w-100">
                    <i class="fas fa-filter me-2"></i> Generar Reporte
                </button>
            </div>
        </form>
    </div>

    <!-- TARJETAS KPI Y ALERTAS (TODAS UNIFICADAS) -->
    <div class="row g-3 mb-4">
        
        <!-- KPI 1: RECETAS -->
        <div class="col-xl-2 col-md-4">
            <div class="card stat-card card-blue">
                <div class="card-body">
                    <div class="card-content">
                        <p class="text-white-50 mb-1 text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 0.5px;">Recetas</p>
                        <h3 class="fw-bold mb-0 text-white"><?php echo $total_recetas; ?></h3>
                    </div>
                    <i class="fas fa-prescription stat-icon"></i>
                </div>
            </div>
        </div>

        <!-- KPI 2: UNIDADES -->
        <div class="col-xl-2 col-md-4">
            <div class="card stat-card card-teal">
                <div class="card-body">
                    <div class="card-content">
                        <p class="text-white-50 mb-1 text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 0.5px;">Unidades</p>
                        <h3 class="fw-bold mb-0 text-white"><?php echo number_format($total_unidades); ?></h3>
                    </div>
                    <i class="fas fa-pills stat-icon"></i>
                </div>
            </div>
        </div>

        <!-- KPI 3: MEDICAMENTOS -->
        <div class="col-xl-2 col-md-4">
            <div class="card stat-card card-purple">
                <div class="card-body">
                    <div class="card-content">
                        <p class="text-white-50 mb-1 text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 0.5px;">Medicamentos</p>
                        <h3 class="fw-bold mb-0 text-white"><?php echo $medicamentos_unicos; ?></h3>
                    </div>
                    <i class="fas fa-capsules stat-icon"></i>
                </div>
            </div>
        </div>

        <!-- ALERTA 1: POR VENCER -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card <?php echo ($total_por_vencer > 0) ? 'card-red' : 'card-green'; ?> clickable" 
                 data-bs-toggle="modal" data-bs-target="#modalPorVencer">
                <div class="card-body">
                    <div class="card-content">
                        <p class="text-white-50 mb-1 text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 0.5px;">Por Vencer (30 días)</p>
                        <h3 class="fw-bold mb-0 text-white"><?php echo $total_por_vencer; ?> <small class="fs-6 fw-normal opacity-75">lotes</small></h3>
                    </div>
                    <i class="fas fa-calendar-times stat-icon"></i>
                </div>
            </div>
        </div>

        <!-- ALERTA 2: STOCK CRÍTICO -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card <?php echo ($total_stock_critico > 0) ? 'card-red' : 'card-green'; ?> clickable" 
                 data-bs-toggle="modal" data-bs-target="#modalStockCritico">
                <div class="card-body">
                    <div class="card-content">
                        <p class="text-white-50 mb-1 text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 0.5px;">Stock Crítico</p>
                        <h3 class="fw-bold mb-0 text-white"><?php echo $total_stock_critico; ?> <small class="fs-6 fw-normal opacity-75">ítems</small></h3>
                    </div>
                    <i class="fas fa-box-open stat-icon"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- GRÁFICOS -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6 col-md-12">
            <div class="rep-card">
                <div class="rep-card-header"><h6><i class="fas fa-chart-bar"></i> Top 10 Medicamentos Recetados</h6></div>
                <div class="chart-box"><canvas id="chartMedicamentos"></canvas></div>
            </div>
        </div>
        <div class="col-lg-6 col-md-12">
            <div class="rep-card">
                <div class="rep-card-header"><h6><i class="fas fa-chart-pie"></i> Recetas por Estado</h6></div>
                <div class="chart-box"><canvas id="chartEstados"></canvas></div>
            </div>
        </div>
        <div class="col-lg-6 col-md-12">
            <div class="rep-card">
                <div class="rep-card-header"><h6><i class="fas fa-user-md"></i> Top 5 Doctores</h6></div>
                <div class="chart-box"><canvas id="chartDoctores"></canvas></div>
            </div>
        </div>
        <div class="col-lg-6 col-md-12">
            <div class="rep-card">
                <div class="rep-card-header"><h6><i class="fas fa-chart-line"></i> Recetas por Mes (últimos 12)</h6></div>
                <div class="chart-box"><canvas id="chartMeses"></canvas></div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL 1: LOTES PRÓXIMOS A VENCER                             -->
<!-- ============================================================ -->
<div class="modal fade" id="modalPorVencer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header border-0" style="background: linear-gradient(135deg, #b45309 0%, #f59e0b 100%); padding: 24px 30px;">
                <h5 class="modal-title text-white fw-bold"><i class="fas fa-calendar-times me-2"></i> Lotes Próximos a Vencer (30 días)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-modal align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Medicamento</th>
                                <th>Stock Actual</th>
                                <th>Fecha Vencimiento</th>
                                <th class="text-center">Días Restantes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($total_por_vencer > 0): ?>
                                <?php 
                                $por_vencer_modal = $conn->query("
                                    SELECT m.nombre, l.cantidad, l.fecha_vencimiento
                                    FROM lotes l JOIN medicamentos m ON l.medicamento_id = m.id
                                    WHERE l.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                                    ORDER BY l.fecha_vencimiento ASC
                                ");
                                while ($m = $por_vencer_modal->fetch_assoc()):
                                    $dias = (new DateTime())->diff(new DateTime($m['fecha_vencimiento']))->days;
                                    $es_critico = $dias <= 7;
                                ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-dark"><i class="fas fa-capsules text-warning me-2"></i><?php echo htmlspecialchars($m['nombre']); ?></td>
                                        <td><span class="badge bg-light text-dark border px-3 py-2"><?php echo (int)$m['cantidad']; ?> uds.</span></td>
                                        <td class="text-muted fw-semibold"><?php echo date('d/m/Y', strtotime($m['fecha_vencimiento'])); ?></td>
                                        <td class="text-center">
                                            <span class="badge <?php echo $es_critico ? 'bg-danger' : 'bg-warning text-dark'; ?> rounded-pill px-3 py-2">
                                                <?php echo $dias; ?> días
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center py-5 text-muted">
                                    <i class="fas fa-check-circle fa-4x text-success mb-3 d-block opacity-50"></i>
                                    <h5 class="fw-bold">No hay lotes próximos a vencer</h5>
                                    <p class="mb-0">El inventario se encuentra en perfecto estado.</p>
                                </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light justify-content-center py-3">
                <button type="button" class="btn btn-secondary rounded-pill px-5 fw-bold" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL 2: STOCK CRÍTICO                                       -->
<!-- ============================================================ -->
<div class="modal fade" id="modalStockCritico" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header border-0" style="background: linear-gradient(135deg, #991b1b 0%, #ef4444 100%); padding: 24px 30px;">
                <h5 class="modal-title text-white fw-bold"><i class="fas fa-box-open me-2"></i> Medicamentos con Stock Crítico (&lt; 10 uds)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-modal align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Medicamento</th>
                                <th>Stock Actual</th>
                                <th class="text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($total_stock_critico > 0): ?>
                                <?php 
                                $stock_critico_modal = $conn->query("
                                    SELECT m.nombre, COALESCE(SUM(l.cantidad), 0) AS stock
                                    FROM medicamentos m LEFT JOIN lotes l ON m.id = l.medicamento_id
                                    GROUP BY m.id, m.nombre HAVING stock < 10 ORDER BY stock ASC
                                ");
                                while ($m = $stock_critico_modal->fetch_assoc()):
                                    $stock = (int)$m['stock'];
                                    $es_critico = $stock < 5;
                                ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-dark"><i class="fas fa-pills text-danger me-2"></i><?php echo htmlspecialchars($m['nombre']); ?></td>
                                        <td><span class="badge bg-light text-dark border px-3 py-2"><?php echo $stock; ?> uds.</span></td>
                                        <td class="text-center">
                                            <span class="badge <?php echo $es_critico ? 'bg-danger' : 'bg-warning text-dark'; ?> rounded-pill px-3 py-2">
                                                <?php echo $es_critico ? '¡CRÍTICO!' : 'Bajo'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center py-5 text-muted">
                                    <i class="fas fa-check-circle fa-4x text-success mb-3 d-block opacity-50"></i>
                                    <h5 class="fw-bold">El inventario está saludable</h5>
                                    <p class="mb-0">No hay medicamentos con stock bajo.</p>
                                </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light justify-content-center py-3">
                <button type="button" class="btn btn-secondary rounded-pill px-5 fw-bold" data-bs-dismiss="modal">Cerrar</button>
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
        if (c) c.parentElement.innerHTML = '<div class="text-center py-5 text-muted"><i class="fas fa-inbox fa-3x mb-3 d-block opacity-25"></i><p class="mb-0 fw-semibold">Sin datos en este rango</p></div>';
    };
    
    const medsData = <?php echo json_encode($meds_data); ?>;
    if (medsData.length) {
        new Chart(document.getElementById('chartMedicamentos'), {
            type: 'bar', data: { labels: <?php echo json_encode($meds_labels); ?>, datasets: [{ label: 'Unidades', data: medsData, backgroundColor: PALETA, borderRadius: 8 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
        });
    } else sinDatos('chartMedicamentos');
    
    const estData = <?php echo json_encode($estados_data); ?>;
    if (estData.length) {
        new Chart(document.getElementById('chartEstados'), {
            type: 'doughnut', data: { labels: <?php echo json_encode($estados_labels); ?>, datasets: [{ data: estData, backgroundColor: ['#10b981','#f59e0b','#ef4444','#3b82f6','#7c3aed'], borderWidth: 0 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { padding: 20, font: { size: 13, weight: 'bold' } } } }, cutout: '70%' }
        });
    } else sinDatos('chartEstados');
    
    const docData = <?php echo json_encode($doc_data); ?>;
    if (docData.length) {
        new Chart(document.getElementById('chartDoctores'), {
            type: 'bar', data: { labels: <?php echo json_encode($doc_labels); ?>, datasets: [{ label: 'Recetas', data: docData, backgroundColor: '#3b82f6', borderRadius: 8 }] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
        });
    } else sinDatos('chartDoctores');
    
    const mesData = <?php echo json_encode($meses_data); ?>;
    if (mesData.length) {
        new Chart(document.getElementById('chartMeses'), {
            type: 'line', data: { labels: <?php echo json_encode($meses_labels); ?>, datasets: [{ label: 'Recetas', data: mesData, borderColor: '#7c3aed', backgroundColor: 'rgba(124,58,237,0.1)', fill: true, tension: 0.4, pointBackgroundColor: '#7c3aed', pointRadius: 5, pointHoverRadius: 7, borderWidth: 3 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
        });
    } else sinDatos('chartMeses');
});
</script>

<?php require_once '../../includes/footer.php'; ?>