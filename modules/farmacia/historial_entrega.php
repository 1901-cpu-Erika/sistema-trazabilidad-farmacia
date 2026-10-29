<?php
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('America/Managua');
require_once '../../config/database.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] != 'farmacia') {
    header("Location: ../../login.php");
    exit();
}

$titulo_pagina = "Historial de Entregas";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$por_pagina = 6;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_actual - 1) * $por_pagina;

$total_entregas_query = $conn->query("SELECT COUNT(*) as t FROM entregas");
$total_entregas = $total_entregas_query->fetch_assoc()['t'];
$total_paginas = ceil($total_entregas / $por_pagina);

$sql = "SELECT 
            e.id,
            e.fecha_entrega,
            e.observaciones,
            r.id AS receta_id,
            r.fecha_receta,
            CONCAT(p.nombre, ' ', p.apellido) AS paciente_nombre,
            p.telefono AS paciente_telefono,
            u.nombre AS doctor_nombre
        FROM entregas e
        JOIN recetas r ON e.receta_id = r.id
        JOIN pacientes p ON r.paciente_id = p.id
        JOIN usuarios u ON r.doctor_id = u.id
        ORDER BY e.fecha_entrega DESC
        LIMIT $por_pagina OFFSET $offset";

$entregas = $conn->query($sql);

$entregas_hoy = $conn->query("SELECT COUNT(*) as t FROM entregas WHERE DATE(fecha_entrega) = CURDATE()")->fetch_assoc()['t'];
$entregas_mes = $conn->query("SELECT COUNT(*) as t FROM entregas WHERE MONTH(fecha_entrega) = MONTH(CURDATE()) AND YEAR(fecha_entrega) = YEAR(CURDATE())")->fetch_assoc()['t'];
?>

<style>
    .main-content { padding-top: 0 !important; }
    .container-fluid.px-4 { margin-top: -15px; }

    :root {
        --hi-primary: #8b5cf6;
        --hi-primary-dark: #6d28d9;
    }

    .hi-hero {
        background: linear-gradient(135deg, #4c1d95 0%, #7c3aed 55%, #a78bfa 100%);
        border-radius: 24px;
        padding: 32px 36px;
        color: #fff;
        position: relative;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(124, 58, 237, 0.25);
        margin-bottom: 24px;
    }
    .hi-hero::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background-image: radial-gradient(circle, rgba(255,255,255,0.12) 1px, transparent 1px);
        background-size: 20px 20px;
        opacity: 0.6;
    }
    .hi-hero::after {
        content: '\f1da';
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        position: absolute;
        right: -20px; bottom: -40px;
        font-size: 200px;
        opacity: 0.08;
        transform: rotate(-10deg);
    }
    .hi-hero-content { position: relative; z-index: 1; }
    .hi-hero-icon {
        width: 68px; height: 68px;
        background: rgba(255,255,255,0.2);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.7rem;
        flex-shrink: 0;
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    }
    .hi-hero h2 { font-size: 1.7rem; font-weight: 700; margin: 0 0 4px 0; }
    .hi-hero p { margin: 0; opacity: 0.9; font-size: 0.92rem; }

    .hi-kpi {
        background: #fff;
        border-radius: 18px;
        padding: 20px 22px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05);
        transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        height: 100%;
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .hi-kpi::before {
        content: '';
        position: absolute;
        top: 0; left: 0;
        width: 4px; height: 100%;
        background: var(--kpi-color, #8b5cf6);
        transition: width 0.35s;
    }
    .hi-kpi:hover { transform: translateY(-5px); box-shadow: 0 14px 32px rgba(15, 23, 42, 0.1); }
    .hi-kpi:hover::before { width: 8px; }
    .hi-kpi-icon {
        width: 56px; height: 56px;
        border-radius: 16px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.4rem; color: #fff;
        flex-shrink: 0;
        box-shadow: 0 6px 16px rgba(0,0,0,0.15);
        transition: transform 0.35s;
    }
    .hi-kpi:hover .hi-kpi-icon { transform: scale(1.1) rotate(-5deg); }
    .hi-kpi-label {
        font-size: 0.72rem; font-weight: 700; color: #94a3b8;
        text-transform: uppercase; letter-spacing: 0.8px;
        margin-bottom: 4px; display: block;
    }
    .hi-kpi-value {
        font-size: 1.9rem; font-weight: 800; color: #0f172a;
        line-height: 1; margin: 0;
    }
    .hi-kpi-sub {
        font-size: 0.75rem;
        color: #94a3b8;
        margin-top: 3px;
    }

    .hi-table-card {
        background: #fff;
        border-radius: 20px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 6px 22px rgba(15, 23, 42, 0.05);
        overflow: hidden;
    }
    .hi-table-header {
        padding: 20px 24px;
        background: linear-gradient(180deg, #fafbfc, #f8fafc);
        border-bottom: 1px solid #f1f5f9;
        display: flex; justify-content: space-between; align-items: center;
        flex-wrap: wrap; gap: 12px;
    }
    .hi-table-header h6 { margin: 0; font-weight: 700; color: #0f172a; font-size: 0.95rem; }
    .hi-count {
        background: var(--hi-primary); color: #fff;
        padding: 6px 14px; border-radius: 20px;
        font-size: 0.75rem; font-weight: 700;
    }
    .hi-search { position: relative; max-width: 280px; }
    .hi-search input {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        padding: 9px 14px 9px 38px;
        font-size: 0.85rem; width: 100%;
        transition: all 0.2s; background: #f8fafc;
    }
    .hi-search input:focus {
        border-color: var(--hi-primary);
        box-shadow: 0 0 0 4px rgba(139,92,246,0.12);
        background: #fff; outline: none;
    }
    .hi-search::before {
        content: '\f002';
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        position: absolute;
        left: 14px; top: 50%;
        transform: translateY(-50%);
        color: #94a3b8; font-size: 0.85rem;
    }

    .hi-table { margin: 0; font-size: 0.87rem; }
    .hi-table thead th {
        background: #f8fafc;
        font-size: 0.7rem; text-transform: uppercase;
        letter-spacing: 0.8px; font-weight: 700;
        color: #64748b; padding: 14px 18px;
        border-bottom: 1px solid #e2e8f0;
    }
    .hi-table tbody td {
        padding: 14px 18px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }
    .hi-table tbody tr { transition: all 0.18s; }
    .hi-table tbody tr:hover {
        background: linear-gradient(90deg, rgba(139,92,246,0.03), transparent);
    }

    .hi-avatar {
        width: 40px; height: 40px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 700; font-size: 0.85rem;
        flex-shrink: 0;
        box-shadow: 0 4px 10px rgba(0,0,0,0.12);
    }

    .hi-badge {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 5px 12px; border-radius: 20px;
        font-size: 0.73rem; font-weight: 700;
    }
    .hi-badge-purple { background: #ede9fe; color: #6d28d9; }

    .hi-date {
        display: flex;
        flex-direction: column;
        gap: 3px;
    }
    .hi-date-day {
        font-weight: 700;
        color: #0f172a;
        font-size: 0.85rem;
    }
    .hi-date-time {
        font-size: 0.72rem;
        color: #94a3b8;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .hi-obs {
        font-size: 0.8rem;
        color: #64748b;
        max-width: 220px;
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .hi-obs-empty {
        font-size: 0.78rem;
        color: #cbd5e1;
        font-style: italic;
    }

    .hi-alert-empty {
        text-align: center;
        padding: 60px 20px;
    }
    .hi-alert-empty i {
        font-size: 4rem;
        color: #cbd5e1;
        margin-bottom: 16px;
        display: block;
        opacity: 0.5;
    }
    .hi-alert-empty h5 {
        color: #475569;
        font-weight: 700;
        margin-bottom: 6px;
    }
    .hi-alert-empty p {
        color: #94a3b8;
        margin-bottom: 20px;
    }

    .hi-btn-primary {
        background: linear-gradient(135deg, #6d28d9, #8b5cf6);
        color: #fff;
        border: none;
        padding: 11px 26px;
        border-radius: 50px;
        font-weight: 700;
        font-size: 0.85rem;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 6px 16px rgba(139,92,246,0.3);
        text-decoration: none;
    }
    .hi-btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 24px rgba(139,92,246,0.45);
        color: #fff;
    }

    .pagination { gap: 6px; }
    .page-item .page-link {
        border-radius: 50%;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #e9ecef;
        color: #6d28d9;
        font-weight: 700;
        transition: all 0.2s;
    }
    .page-item.active .page-link {
        background: linear-gradient(135deg, #4c1d95, #7c3aed);
        color: white;
        border-color: transparent;
        box-shadow: 0 4px 10px rgba(124,58,237,0.3);
    }
    .page-item .page-link:hover {
        background: #f8f9fa;
        border-color: #7c3aed;
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

    @media (max-width: 768px) {
        .hi-hero { padding: 22px; }
        .hi-hero h2 { font-size: 1.3rem; }
        .hi-search { max-width: 100%; }
    }
</style>

<div class="container-fluid px-4 fade-in">
    <div class="hi-hero">
        <div class="hi-hero-content d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center">
                <div class="hi-hero-icon me-3">
                    <i class="fas fa-history"></i>
                </div>
                <div>
                    <h2>Historial de Entregas</h2>
                    <p><i class="fas fa-clipboard-check me-2"></i>Registro completo de todas las entregas realizadas</p>
                </div>
            </div>
            <a href="dashboard.php" class="btn btn-light rounded-pill px-4 fw-semibold">
                <i class="fas fa-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-lg-4 col-md-6 stagger-1">
            <div class="hi-kpi" style="--kpi-color: #8b5cf6;">
                <div class="hi-kpi-icon" style="background: linear-gradient(135deg, #6d28d9, #8b5cf6);">
                    <i class="fas fa-truck"></i>
                </div>
                <div>
                    <span class="hi-kpi-label">Total Entregas</span>
                    <div class="hi-kpi-value"><?php echo $total_entregas; ?></div>
                    <div class="hi-kpi-sub">Registros históricos</div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 stagger-2">
            <div class="hi-kpi" style="--kpi-color: #10b981;">
                <div class="hi-kpi-icon" style="background: linear-gradient(135deg, #065f46, #10b981);">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div>
                    <span class="hi-kpi-label">Entregas Hoy</span>
                    <div class="hi-kpi-value"><?php echo $entregas_hoy; ?></div>
                    <div class="hi-kpi-sub"><?php echo date('d/m/Y'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 stagger-3">
            <div class="hi-kpi" style="--kpi-color: #3b82f6;">
                <div class="hi-kpi-icon" style="background: linear-gradient(135deg, #1e40af, #3b82f6);">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div>
                    <span class="hi-kpi-label">Este Mes</span>
                    <div class="hi-kpi-value"><?php echo $entregas_mes; ?></div>
                    <div class="hi-kpi-sub"><?php echo date('F Y'); ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="hi-table-card stagger-4">
        <div class="hi-table-header">
            <h6><i class="fas fa-list me-2" style="color: #8b5cf6;"></i>Registro de Entregas</h6>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="hi-search">
                    <input type="text" id="buscar" placeholder="Buscar paciente, doctor...">
                </div>
                <span class="hi-count"><?php echo $total_entregas; ?> entregas</span>
            </div>
        </div>
        <div class="table-responsive">
            <?php if ($entregas && $entregas->num_rows > 0): ?>
                <table class="table hi-table" id="tablaEntregas">
                    <thead>
                        <tr>
                            <th style="width: 6%;">ID</th>
                            <th style="width: 26%;">Paciente</th>
                            <th style="width: 22%;">Doctor</th>
                            <th style="width: 10%;">Receta</th>
                            <th style="width: 18%;">Fecha Entrega</th>
                            <th style="width: 18%;">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($e = $entregas->fetch_assoc()): 
                        $inicial_pac = strtoupper(substr($e['paciente_nombre'], 0, 2));
                    ?>
                        <tr>
                            <td>
                                <span class="badge bg-light text-secondary rounded-pill px-2 py-1">
                                    #<?php echo $e['id']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="hi-avatar" style="background: <?php echo '#' . substr(md5($e['paciente_nombre']), 0, 6); ?>;">
                                        <?php echo $inicial_pac; ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($e['paciente_nombre']); ?></div>
                                        <?php if (!empty($e['paciente_telefono'])): ?>
                                            <small class="text-muted"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($e['paciente_telefono']); ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">Sin teléfono</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="hi-avatar" style="background: linear-gradient(135deg, #065f46, #10b981); font-size: 0.75rem;">
                                        <i class="fas fa-user-md"></i>
                                    </div>
                                    <div class="fw-semibold text-dark">
                                        <?php echo htmlspecialchars($e['doctor_nombre']); ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="hi-badge hi-badge-purple">
                                    <i class="fas fa-prescription"></i> #<?php echo $e['receta_id']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="hi-date">
                                    <div class="hi-date-day">
                                        <i class="fas fa-calendar-day me-1" style="color: #8b5cf6;"></i>
                                        <?php echo date('d/m/Y', strtotime($e['fecha_entrega'])); ?>
                                    </div>
                                    <div class="hi-date-time">
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('H:i', strtotime($e['fecha_entrega'])); ?> hrs
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($e['observaciones'])): ?>
                                    <span class="hi-obs" title="<?php echo htmlspecialchars($e['observaciones']); ?>">
                                        <i class="fas fa-comment me-1" style="color: #8b5cf6;"></i>
                                        <?php echo htmlspecialchars($e['observaciones']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="hi-obs-empty">Sin observaciones</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="hi-alert-empty">
                    <i class="fas fa-truck"></i>
                    <h5>No hay entregas registradas</h5>
                    <p>Las entregas aparecerán aquí cuando confirmes la entrega de una receta.</p>
                    <a href="recetas_recibidas.php" class="hi-btn-primary">
                        <i class="fas fa-prescription"></i> Ir a Recetas Recibidas
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($total_paginas > 1): ?>
        <div class="card-footer bg-white border-0 py-3">
            <nav>
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?php echo $pagina_actual <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?pagina=<?php echo $pagina_actual - 1; ?>"><i class="fas fa-chevron-left"></i></a>
                    </li>
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <li class="page-item <?php echo $pagina_actual == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $pagina_actual >= $total_paginas ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?pagina=<?php echo $pagina_actual + 1; ?>"><i class="fas fa-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('buscar');
    if (input) {
        input.addEventListener('keyup', function() {
            const filter = this.value.toUpperCase();
            const rows = document.querySelectorAll('#tablaEntregas tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toUpperCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>