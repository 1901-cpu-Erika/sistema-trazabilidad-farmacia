<?php
if (session_status() === PHP_SESSION_NONE) session_save_path('/tmp'); // Esto es clave para Vercel
    session_start();
date_default_timezone_set('America/Managua');
require_once '../../config/database.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header("Location: ../../login.php");
    exit();
}

$titulo_pagina = "Reportes y Estadísticas";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$total_usuarios = $conn->query("SELECT COUNT(*) t FROM usuarios")->fetch_assoc()['t'];
$total_doctores = $conn->query("SELECT COUNT(*) t FROM usuarios WHERE tipo='doctor'")->fetch_assoc()['t'];
$total_jefes    = $conn->query("SELECT COUNT(*) t FROM usuarios WHERE tipo='jefe_farmacia'")->fetch_assoc()['t'];
$total_farmacia = $conn->query("SELECT COUNT(*) t FROM usuarios WHERE tipo='farmacia'")->fetch_assoc()['t'];
$total_admins   = $conn->query("SELECT COUNT(*) t FROM usuarios WHERE tipo='admin'")->fetch_assoc()['t'];

$total_recetas = $conn->query("SELECT COUNT(*) t FROM recetas")->fetch_assoc()['t'];
$total_pacientes = $conn->query("SELECT COUNT(*) t FROM pacientes")->fetch_assoc()['t'];

$sql_esp = "SELECT e.nombre, COUNT(u.id) AS total 
            FROM especialidades e
            LEFT JOIN usuarios u ON u.especialidad_id = e.id AND u.tipo = 'doctor'
            GROUP BY e.id, e.nombre
            HAVING total > 0
            ORDER BY total DESC";
$res_esp = $conn->query($sql_esp);
$esp_labels = [];
$esp_data = [];
while ($row = $res_esp->fetch_assoc()) {
    $esp_labels[] = $row['nombre'];
    $esp_data[] = (int)$row['total'];
}

$sql_top = "SELECT u.nombre, COUNT(r.id) AS total 
            FROM usuarios u
            LEFT JOIN recetas r ON r.doctor_id = u.id
            WHERE u.tipo = 'doctor'
            GROUP BY u.id, u.nombre
            HAVING total > 0
            ORDER BY total DESC
            LIMIT 5";
$res_top = $conn->query($sql_top);
$top_labels = [];
$top_data = [];
while ($row = $res_top->fetch_assoc()) {
    $top_labels[] = $row['nombre'];
    $top_data[] = (int)$row['total'];
}

$sql_roles = "SELECT tipo, COUNT(*) AS total FROM usuarios GROUP BY tipo";
$res_roles = $conn->query($sql_roles);
$roles_labels = [];
$roles_data = [];
$roles_map = [
    'admin' => 'Administrador',
    'jefe_farmacia' => 'Jefe Farmacia',
    'doctor' => 'Doctor',
    'farmacia' => 'Farmacia'
];
while ($row = $res_roles->fetch_assoc()) {
    $roles_labels[] = $roles_map[$row['tipo']] ?? ucfirst($row['tipo']);
    $roles_data[] = (int)$row['total'];
}

$sql_meses = "SELECT DATE_FORMAT(created_at, '%Y-%m') AS mes, COUNT(*) AS total 
              FROM usuarios 
              WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
              GROUP BY mes
              ORDER BY mes ASC";
$res_meses = $conn->query($sql_meses);
$meses_labels = [];
$meses_data = [];
while ($row = $res_meses->fetch_assoc()) {
    $meses_labels[] = $row['mes'];
    $meses_data[] = (int)$row['total'];
}

$sql_estados = "SELECT estado, COUNT(*) AS total FROM usuarios GROUP BY estado";
$res_estados = $conn->query($sql_estados);
$estados_labels = [];
$estados_data = [];
while ($row = $res_estados->fetch_assoc()) {
    $estados_labels[] = ucfirst($row['estado']);
    $estados_data[] = (int)$row['total'];
}
?>

<style>
    :root {
        --rep-primary: #dc2626;
        --rep-primary-dark: #7f1d1d;
    }

    .rep-hero {
        background: linear-gradient(135deg, #7f1d1d 0%, #dc2626 55%, #ef4444 100%);
        border-radius: 24px;
        padding: 32px 36px;
        color: #fff;
        position: relative;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(220, 38, 38, 0.25);
        margin-bottom: 24px;
    }
    .rep-hero::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background-image: radial-gradient(circle, rgba(255,255,255,0.12) 1px, transparent 1px);
        background-size: 20px 20px;
        opacity: 0.6;
    }
    .rep-hero::after {
        content: '\f080';
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        position: absolute;
        right: -20px; bottom: -40px;
        font-size: 200px;
        opacity: 0.08;
        transform: rotate(-10deg);
    }
    .rep-hero-content { position: relative; z-index: 1; }
    .rep-hero-icon {
        width: 68px; height: 68px;
        background: rgba(255,255,255,0.2);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.7rem;
        flex-shrink: 0;
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    }
    .rep-hero h2 { font-size: 1.7rem; font-weight: 700; margin: 0 0 4px 0; }
    .rep-hero p { margin: 0; opacity: 0.9; font-size: 0.92rem; }

    .rep-kpi {
        background: #fff;
        border-radius: 18px;
        padding: 18px 20px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05);
        transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        height: 100%;
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .rep-kpi::before {
        content: '';
        position: absolute;
        top: 0; left: 0;
        width: 4px; height: 100%;
        background: var(--kpi-color, #dc2626);
        transition: width 0.35s;
    }
    .rep-kpi:hover { transform: translateY(-5px); box-shadow: 0 14px 32px rgba(15, 23, 42, 0.1); }
    .rep-kpi:hover::before { width: 8px; }
    .rep-kpi-icon {
        width: 50px; height: 50px;
        border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.25rem; color: #fff;
        flex-shrink: 0;
        box-shadow: 0 6px 16px rgba(0,0,0,0.15);
        transition: transform 0.35s;
    }
    .rep-kpi:hover .rep-kpi-icon { transform: scale(1.1) rotate(-5deg); }
    .rep-kpi-label {
        font-size: 0.68rem; font-weight: 700; color: #94a3b8;
        text-transform: uppercase; letter-spacing: 0.8px;
        margin-bottom: 3px; display: block;
    }
    .rep-kpi-value {
        font-size: 1.6rem; font-weight: 800; color: #0f172a;
        line-height: 1; margin: 0;
    }

    .rep-card {
        background: #fff;
        border-radius: 20px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 6px 22px rgba(15, 23, 42, 0.05);
        overflow: hidden;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    .rep-card-header {
        padding: 18px 22px;
        background: linear-gradient(180deg, #fafbfc, #f8fafc);
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .rep-card-header h6 {
        margin: 0;
        font-weight: 700;
        color: #0f172a;
        font-size: 0.92rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .rep-card-header h6 i {
        width: 30px; height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #fef2f2;
        color: #dc2626;
        border-radius: 10px;
        font-size: 0.85rem;
    }
    .rep-card-body { padding: 20px 22px; flex: 1; }

    .rep-chart-wrapper {
        position: relative;
        width: 100%;
        height: 300px;
    }

    .rep-table { margin: 0; font-size: 0.85rem; }
    .rep-table thead th {
        background: #f8fafc;
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        font-weight: 700;
        color: #64748b;
        padding: 12px 16px;
        border-bottom: 1px solid #e2e8f0;
    }
    .rep-table tbody td {
        padding: 12px 16px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }
    .rep-table tbody tr:hover {
        background: linear-gradient(90deg, rgba(220,38,38,0.03), transparent);
    }

    .rep-avatar {
        width: 38px; height: 38px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 700; font-size: 0.82rem;
        flex-shrink: 0;
        box-shadow: 0 4px 10px rgba(0,0,0,0.12);
    }

    .rep-badge {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 5px 12px; border-radius: 20px;
        font-size: 0.72rem; font-weight: 700;
    }
    .rep-badge-admin    { background: #1e293b; color: #fff; }
    .rep-badge-jefe     { background: #dcfce7; color: #166534; }
    .rep-badge-farmacia { background: #ede9fe; color: #6d28d9; }
    .rep-badge-doctor   { background: #dbeafe; color: #1e40af; }
    .rep-badge-turno    { background: #fef3c7; color: #92400e; }

    .rep-bar {
        height: 8px;
        border-radius: 4px;
        background: #f1f5f9;
        overflow: hidden;
        margin-top: 6px;
    }
    .rep-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #dc2626, #ef4444);
        border-radius: 4px;
        transition: width 1s ease;
    }

    .fade-in { animation: fadeUp 0.5s ease forwards; }
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(20px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .stagger-1 { animation: fadeUp 0.5s ease 0.05s backwards; }
    .stagger-2 { animation: fadeUp 0.5s ease 0.10s backwards; }
    .stagger-3 { animation: fadeUp 0.5s ease 0.15s backwards; }
    .stagger-4 { animation: fadeUp 0.5s ease 0.20s backwards; }
    .stagger-5 { animation: fadeUp 0.5s ease 0.25s backwards; }

    @media (max-width: 768px) {
        .rep-hero { padding: 22px; }
        .rep-hero h2 { font-size: 1.3rem; }
        .rep-chart-wrapper { height: 250px; }
    }
</style>

<div class="container-fluid px-4 fade-in">

    <div class="rep-hero">
        <div class="rep-hero-content d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center">
                <div class="rep-hero-icon me-3">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <h2>Reportes y Estadísticas</h2>
                    <p><i class="fas fa-users-cog me-2"></i>Análisis del personal y actividad del sistema</p>
                </div>
            </div>
            <a href="dashboard.php" class="btn btn-light rounded-pill px-4 fw-semibold">
                <i class="fas fa-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6 stagger-1">
            <div class="rep-kpi" style="--kpi-color: #dc2626;">
                <div class="rep-kpi-icon" style="background: linear-gradient(135deg, #7f1d1d, #dc2626);">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <span class="rep-kpi-label">Total Usuarios</span>
                    <div class="rep-kpi-value"><?php echo $total_usuarios; ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 stagger-2">
            <div class="rep-kpi" style="--kpi-color: #3b82f6;">
                <div class="rep-kpi-icon" style="background: linear-gradient(135deg, #1e40af, #3b82f6);">
                    <i class="fas fa-user-md"></i>
                </div>
                <div>
                    <span class="rep-kpi-label">Doctores</span>
                    <div class="rep-kpi-value"><?php echo $total_doctores; ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 stagger-3">
            <div class="rep-kpi" style="--kpi-color: #10b981;">
                <div class="rep-kpi-icon" style="background: linear-gradient(135deg, #065f46, #10b981);">
                    <i class="fas fa-prescription"></i>
                </div>
                <div>
                    <span class="rep-kpi-label">Recetas Emitidas</span>
                    <div class="rep-kpi-value"><?php echo $total_recetas; ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 stagger-4">
            <div class="rep-kpi" style="--kpi-color: #7c3aed;">
                <div class="rep-kpi-icon" style="background: linear-gradient(135deg, #4c1d95, #7c3aed);">
                    <i class="fas fa-user-injured"></i>
                </div>
                <div>
                    <span class="rep-kpi-label">Pacientes</span>
                    <div class="rep-kpi-value"><?php echo $total_pacientes; ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6 stagger-1">
            <div class="rep-card">
                <div class="rep-card-header">
                    <h6><i class="fas fa-chart-pie"></i> Doctores por Especialidad</h6>
                </div>
                <div class="rep-card-body">
                    <div class="rep-chart-wrapper">
                        <canvas id="chartEspecialidades"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 stagger-2">
            <div class="rep-card">
                <div class="rep-card-header">
                    <h6><i class="fas fa-user-tag"></i> Distribución por Rol</h6>
                </div>
                <div class="rep-card-body">
                    <div class="rep-chart-wrapper">
                        <canvas id="chartRoles"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 stagger-3">
            <div class="rep-card">
                <div class="rep-card-header">
                    <h6><i class="fas fa-trophy"></i> Top Doctores por Recetas</h6>
                </div>
                <div class="rep-card-body">
                    <div class="rep-chart-wrapper">
                        <canvas id="chartTopDoctores"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 stagger-4">
            <div class="rep-card">
                <div class="rep-card-header">
                    <h6><i class="fas fa-chart-line"></i> Usuarios Registrados por Mes</h6>
                </div>
                <div class="rep-card-body">
                    <div class="rep-chart-wrapper">
                        <canvas id="chartMeses"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 stagger-5">
            <div class="rep-card">
                <div class="rep-card-header">
                    <h6><i class="fas fa-list"></i> Usuarios por Estado</h6>
                </div>
                <div class="rep-card-body">
                    <?php if (count($estados_labels) > 0): ?>
                        <?php foreach ($estados_labels as $i => $label): 
                            $total = $estados_data[$i];
                            $porcentaje = $total_usuarios > 0 ? round(($total / $total_usuarios) * 100) : 0;
                        ?>
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="fw-bold" style="color: #0f172a; font-size: 0.9rem;">
                                        <i class="fas fa-circle me-2" style="color: <?php echo $label == 'Activo' ? '#10b981' : '#94a3b8'; ?>; font-size: 0.6rem;"></i>
                                        <?php echo $label; ?>
                                    </div>
                                    <div>
                                        <span class="fw-bold" style="color: #0f172a;"><?php echo $total; ?></span>
                                        <small class="text-muted ms-2">(<?php echo $porcentaje; ?>%)</small>
                                    </div>
                                </div>
                                <div class="rep-bar">
                                    <div class="rep-bar-fill" style="width: <?php echo $porcentaje; ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-inbox fa-2x mb-2 d-block opacity-50"></i>
                            No hay datos disponibles
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const PALETA = ['#dc2626','#ef4444','#f87171','#fca5a5','#7c3aed','#3b82f6','#10b981','#f59e0b'];

    const espLabels = <?php echo json_encode($esp_labels); ?>;
    const espData   = <?php echo json_encode($esp_data); ?>;

    if (espLabels.length > 0) {
        new Chart(document.getElementById('chartEspecialidades'), {
            type: 'doughnut',
            data: {
                labels: espLabels,
                datasets: [{
                    data: espData,
                    backgroundColor: PALETA,
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 12,
                            font: { size: 11, weight: '600' },
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    }
                }
            }
        });
    } else {
        document.getElementById('chartEspecialidades').parentElement.innerHTML =
            '<div class="text-center text-muted py-5"><i class="fas fa-inbox fa-3x mb-3 d-block opacity-50"></i>Sin datos de especialidades</div>';
    }

    const rolesLabels = <?php echo json_encode($roles_labels); ?>;
    const rolesData   = <?php echo json_encode($roles_data); ?>;

    if (rolesLabels.length > 0) {
        new Chart(document.getElementById('chartRoles'), {
            type: 'bar',
            data: {
                labels: rolesLabels,
                datasets: [{
                    data: rolesData,
                    backgroundColor: ['#dc2626','#10b981','#3b82f6','#8b5cf6'],
                    borderRadius: 10,
                    barThickness: 50
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } }, grid: { color: '#f1f5f9' } },
                    x: { ticks: { font: { size: 11, weight: '600' }, color: '#475569' }, grid: { display: false } }
                }
            }
        });
    } else {
        document.getElementById('chartRoles').parentElement.innerHTML =
            '<div class="text-center text-muted py-5"><i class="fas fa-inbox fa-3x mb-3 d-block opacity-50"></i>Sin datos de roles</div>';
    }

    const topLabels = <?php echo json_encode($top_labels); ?>;
    const topData   = <?php echo json_encode($top_data); ?>;

    if (topLabels.length > 0) {
        new Chart(document.getElementById('chartTopDoctores'), {
            type: 'bar',
            data: {
                labels: topLabels,
                datasets: [{
                    label: 'Recetas',
                    data: topData,
                    backgroundColor: 'rgba(220, 38, 38, 0.85)',
                    borderRadius: 8,
                    barThickness: 28
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } }, grid: { color: '#f1f5f9' } },
                    y: { ticks: { font: { size: 11, weight: '600' }, color: '#475569' }, grid: { display: false } }
                }
            }
        });
    } else {
        document.getElementById('chartTopDoctores').parentElement.innerHTML =
            '<div class="text-center text-muted py-5"><i class="fas fa-inbox fa-3x mb-3 d-block opacity-50"></i>Sin recetas registradas</div>';
    }

    const mesLabels = <?php echo json_encode($meses_labels); ?>;
    const mesData   = <?php echo json_encode($meses_data); ?>;

    if (mesLabels.length > 0) {
        new Chart(document.getElementById('chartMeses'), {
            type: 'line',
            data: {
                labels: mesLabels,
                datasets: [{
                    label: 'Usuarios',
                    data: mesData,
                    borderColor: '#dc2626',
                    backgroundColor: 'rgba(220, 38, 38, 0.1)',
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#dc2626',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } }, grid: { color: '#f1f5f9' } },
                    x: { ticks: { font: { size: 10 }, color: '#64748b' }, grid: { display: false } }
                }
            }
        });
    } else {
        document.getElementById('chartMeses').parentElement.innerHTML =
            '<div class="text-center text-muted py-5"><i class="fas fa-inbox fa-3x mb-3 d-block opacity-50"></i>Sin registros recientes</div>';
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>