<?php
if (session_status() === PHP_SESSION_NONE) {
    session_save_path('/tmp');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

require_once '../../config/database.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header('Location: /login.php'); 
    exit();
}

$titulo_pagina = "Panel de Administración";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$total_usuarios = $conn->query("SELECT COUNT(*) t FROM usuarios")->fetch_assoc()['t'];
$total_doctores = $conn->query("SELECT COUNT(*) t FROM usuarios WHERE tipo='doctor'")->fetch_assoc()['t'];
$total_jefes    = $conn->query("SELECT COUNT(*) t FROM usuarios WHERE tipo='jefe_farmacia'")->fetch_assoc()['t'];
$total_farmacia = $conn->query("SELECT COUNT(*) t FROM usuarios WHERE tipo='farmacia'")->fetch_assoc()['t'];

$total_fijos = $conn->query("SELECT COUNT(*) t FROM usuarios WHERE tipo='doctor' AND subtipo='fijo'")->fetch_assoc()['t'];
$total_turno = $conn->query("SELECT COUNT(*) t FROM usuarios WHERE tipo='doctor' AND subtipo='turno'")->fetch_assoc()['t'];

$hora = date('H');
if ($hora < 12) $saludo = "Buenos días";
elseif ($hora < 19) $saludo = "Buenas tardes";
else $saludo = "Buenas noches";
?>

<style>
    :root {
        --ad-primary: #dc2626;
        --ad-primary-dark: #7f1d1d;
        --ad-primary-light: #fef2f2;
        --ad-teal: #0891b2;
        --ad-green: #059669;
        --ad-blue: #2563eb;
        --ad-purple: #7c3aed;
        --ad-amber: #d97706;
    }
    .ad-hero {
        background: linear-gradient(135deg, #7f1d1d 0%, #dc2626 55%, #ef4444 100%);
        border-radius: 24px;
        padding: 36px 40px;
        color: #fff;
        position: relative;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(220, 38, 38, 0.25);
        margin-bottom: 28px;
    }

    .ad-hero::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background-image: radial-gradient(circle, rgba(255,255,255,0.12) 1px, transparent 1px);
        background-size: 20px 20px;
        opacity: 0.6;
    }

    .ad-hero::after {
        content: '\f0c0';
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        position: absolute;
        right: -20px;
        bottom: -40px;
        font-size: 220px;
        opacity: 0.08;
        transform: rotate(-10deg);
    }

    .ad-hero-content {
        position: relative;
        z-index: 1;
    }

    .ad-hero-icon {
        width: 72px;
        height: 72px;
        background: rgba(255,255,255,0.2);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        flex-shrink: 0;
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    }

    .ad-hero h2 {
        font-size: 1.75rem;
        font-weight: 700;
        margin: 0 0 4px 0;
        letter-spacing: -0.3px;
    }

    .ad-hero p {
        margin: 0;
        opacity: 0.9;
        font-size: 0.95rem;
    }

    .ad-hero-badge {
        display: inline-block;
        background: rgba(255,255,255,0.2);
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        margin-top: 12px;
    }

    .ad-kpi {
        background: #fff;
        border-radius: 20px;
        padding: 24px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05);
        transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        height: 100%;
        display: flex;
        align-items: center;
        gap: 18px;
    }

    .ad-kpi::before {
        content: '';
        position: absolute;
        top: 0; left: 0;
        width: 4px; height: 100%;
        background: var(--kpi-color, #dc2626);
        transition: width 0.35s;
    }

    .ad-kpi:hover {
        transform: translateY(-6px);
        box-shadow: 0 16px 40px rgba(15, 23, 42, 0.12);
        border-color: transparent;
    }

    .ad-kpi:hover::before {
        width: 8px;
    }

    .ad-kpi-icon {
        width: 60px;
        height: 60px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: #fff;
        flex-shrink: 0;
        box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        transition: transform 0.35s;
    }

    .ad-kpi:hover .ad-kpi-icon {
        transform: scale(1.1) rotate(-5deg);
    }

    .ad-kpi-info {
        flex: 1;
        min-width: 0;
    }

    .ad-kpi-label {
        font-size: 0.72rem;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 4px;
        display: block;
    }

    .ad-kpi-value {
        font-size: 2rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1;
        margin: 0;
    }

    
    .ad-card-color {
        border-radius: 20px;
        padding: 26px;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
        transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        height: 100%;
        cursor: default;
    }

    .ad-card-color::before {
        content: '';
        position: absolute;
        top: -50%; right: -50%;
        width: 200%; height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
        opacity: 0;
        transition: opacity 0.4s;
    }

    .ad-card-color:hover {
        transform: translateY(-6px) scale(1.02);
        box-shadow: 0 20px 45px rgba(0,0,0,0.2);
    }

    .ad-card-color:hover::before {
        opacity: 1;
    }

    .ad-card-color h6 {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        opacity: 0.85;
        margin-bottom: 6px;
        font-weight: 700;
    }

    .ad-card-color h2 {
        font-size: 2.4rem;
        font-weight: 800;
        margin: 0;
        line-height: 1;
    }

    .ad-card-color small {
        opacity: 0.8;
        font-size: 0.8rem;
    }

    .ad-card-color i.ad-big-icon {
        font-size: 3.5rem;
        opacity: 0.35;
        transition: transform 0.4s;
    }

    .ad-card-color:hover i.ad-big-icon {
        transform: scale(1.15) rotate(8deg);
        opacity: 0.5;
    }

    .ad-action-card {
        background: linear-gradient(135deg, #fafafa 0%, #f1f5f9 100%);
        border-radius: 20px;
        padding: 40px;
        text-align: center;
        border: 2px dashed #cbd5e1;
        transition: all 0.35s;
        position: relative;
        overflow: hidden;
    }

    .ad-action-card:hover {
        border-color: var(--ad-primary);
        background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
        border-style: solid;
    }

    .ad-action-icon {
        width: 90px;
        height: 90px;
        border-radius: 24px;
        background: linear-gradient(135deg, #7f1d1d, #dc2626);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        margin-bottom: 20px;
        box-shadow: 0 12px 30px rgba(220, 38, 38, 0.3);
        transition: transform 0.4s;
    }

    .ad-action-card:hover .ad-action-icon {
        transform: scale(1.1) rotate(-5deg);
    }

    .ad-action-card h4 {
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 8px;
    }

    .ad-action-card p {
        color: #64748b;
        margin-bottom: 24px;
    }

    .ad-btn-primary {
        background: linear-gradient(135deg, #7f1d1d, #dc2626);
        color: #fff;
        border: none;
        padding: 14px 40px;
        border-radius: 50px;
        font-weight: 700;
        font-size: 0.95rem;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 8px 24px rgba(220, 38, 38, 0.3);
    }

    .ad-btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 14px 35px rgba(220, 38, 38, 0.45);
        color: #fff;
    }
    .ad-quick-card {
        background: #fff;
        border-radius: 16px;
        padding: 20px;
        text-align: center;
        border: 1px solid #e5e7eb;
        transition: all 0.3s;
        text-decoration: none;
        display: block;
        color: #1e293b;
        height: 100%;
    }

    .ad-quick-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
        border-color: var(--ad-primary);
        color: var(--ad-primary);
    }

    .ad-quick-card i {
        font-size: 1.6rem;
        margin-bottom: 10px;
        display: block;
        color: var(--ad-primary);
    }

    .ad-quick-card small {
        font-size: 0.8rem;
        font-weight: 600;
    }

    
    .fade-in { animation: fadeUp 0.6s ease forwards; }
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .stagger-1 { animation: fadeUp 0.5s ease 0.1s backwards; }
    .stagger-2 { animation: fadeUp 0.5s ease 0.2s backwards; }
    .stagger-3 { animation: fadeUp 0.5s ease 0.3s backwards; }
    .stagger-4 { animation: fadeUp 0.5s ease 0.4s backwards; }

    @media (max-width: 768px) {
        .ad-hero { padding: 24px; }
        .ad-hero h2 { font-size: 1.3rem; }
        .ad-hero-icon { width: 56px; height: 56px; font-size: 1.4rem; }
        .ad-kpi { padding: 18px; }
        .ad-kpi-icon { width: 50px; height: 50px; font-size: 1.2rem; }
        .ad-kpi-value { font-size: 1.6rem; }
    }
</style>

<div class="container-fluid px-4 fade-in">

    <div class="ad-hero">
        <div class="ad-hero-content d-flex align-items-center">
            <div class="ad-hero-icon me-4">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="flex-grow-1">
                <h2><?php echo $saludo; ?>, <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></h2>
                <p>
                    <i class="fas fa-users-cog me-2"></i>
                    Gestión del personal del sistema
                </p>
                <span class="ad-hero-badge">
                    <i class="fas fa-user-shield me-1"></i> Administrador de Personal
                </span>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-xl-3 col-md-6 stagger-1">
            <div class="ad-kpi" style="--kpi-color: #2563eb;">
                <div class="ad-kpi-icon" style="background: linear-gradient(135deg, #1e40af, #3b82f6);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="ad-kpi-info">
                    <span class="ad-kpi-label">Total Usuarios</span>
                    <div class="ad-kpi-value"><?php echo $total_usuarios; ?></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 stagger-2">
            <div class="ad-kpi" style="--kpi-color: #059669;">
                <div class="ad-kpi-icon" style="background: linear-gradient(135deg, #065f46, #10b981);">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="ad-kpi-info">
                    <span class="ad-kpi-label">Jefes de Farmacia</span>
                    <div class="ad-kpi-value"><?php echo $total_jefes; ?></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 stagger-3">
            <div class="ad-kpi" style="--kpi-color: #7c3aed;">
                <div class="ad-kpi-icon" style="background: linear-gradient(135deg, #4c1d95, #7c3aed);">
                    <i class="fas fa-user-nurse"></i>
                </div>
                <div class="ad-kpi-info">
                    <span class="ad-kpi-label">Personal Farmacia</span>
                    <div class="ad-kpi-value"><?php echo $total_farmacia; ?></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 stagger-4">
            <div class="ad-kpi" style="--kpi-color: #dc2626;">
                <div class="ad-kpi-icon" style="background: linear-gradient(135deg, #7f1d1d, #dc2626);">
                    <i class="fas fa-user-md"></i>
                </div>
                <div class="ad-kpi-info">
                    <span class="ad-kpi-label">Doctores</span>
                    <div class="ad-kpi-value"><?php echo $total_doctores; ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-6 stagger-1">
            <div class="ad-card-color" style="background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);">
                <div>
                    <h6><i class="fas fa-user-check me-2"></i>Doctores Fijos</h6>
                    <h2><?php echo $total_fijos; ?></h2>
                    <small><i class="fas fa-circle me-1" style="font-size: 0.5rem; color: #4ade80;"></i> Personal permanente</small>
                </div>
                <i class="fas fa-user-md ad-big-icon"></i>
            </div>
        </div>

        <div class="col-md-6 stagger-2">
            <div class="ad-card-color" style="background: linear-gradient(135deg, #b45309 0%, #f59e0b 100%);">
                <div>
                    <h6><i class="fas fa-clock me-2"></i>Doctores de Turno</h6>
                    <h2><?php echo $total_turno; ?></h2>
                    <small><i class="fas fa-circle me-1" style="font-size: 0.5rem; color: #fcd34d;"></i> Personal temporal</small>
                </div>
                <i class="fas fa-clock ad-big-icon"></i>
            </div>
        </div>
    </div>
    <div class="row mb-4">
        <div class="col-12">
            <div class="ad-action-card">
                <div class="ad-action-icon">
                    <i class="fas fa-users-cog"></i>
                </div>
                <h4>Gestión de Doctores</h4>
                <p>Registra, edita y administra únicamente a los médicos del sistema</p>
                <a href="usuarios.php" class="ad-btn-primary">
                    <i class="fas fa-arrow-right"></i>
                    Ir a Gestión
                </a>
            </div>
        </div>
    </div>

</div>

<?php require_once '../../includes/footer.php'; ?>