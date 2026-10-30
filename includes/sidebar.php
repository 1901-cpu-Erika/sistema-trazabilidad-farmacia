<?php
if (session_status() === PHP_SESSION_NONE) {
    if (file_exists('/tmp')) { session_save_path('/tmp'); } // Fix para Vercel
    session_start();
}

$tipo_usuario   = $_SESSION['tipo_usuario'] ?? 'doctor';
$nombre_usuario = $_SESSION['usuario_nombre'] ?? 'Usuario';

// ==========================================
// ARREGLO DE URL PARA VERCEL Y XAMPP
// ==========================================
$protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];

// Si la URL contiene 'vercel.app', estamos en producción. Si no, en local.
if (strpos($host, 'vercel.app') !== false) {
    $base_url = $protocolo . "://" . $host . "/";
} else {
    $base_url = $protocolo . "://" . $host . "/sistema-trazabilidad-farmacia/";
}
// ==========================================

$archivo_actual = basename($_SERVER['PHP_SELF']);

$color_rol = match($tipo_usuario) {
    'admin'          => '#dc2626',
    'jefe_farmacia'  => '#10b981',
    'doctor'         => '#3b82f6',
    'farmacia'       => '#8b5cf6',
    default          => '#64748b'
};

$nombre_rol = match($tipo_usuario) {
    'admin'          => 'Administrador',
    'jefe_farmacia'  => 'Jefe de Farmacia',
    'doctor'         => 'Doctor',
    'farmacia'       => 'Personal de Farmacia',
    default          => 'Usuario'
};
?>

<style>
    .sidebar {
        background: linear-gradient(180deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
        min-height: 100vh; width: 260px;
        position: fixed; top: 0; left: 0;
        z-index: 1000; overflow-y: auto; padding: 0;
        box-shadow: 4px 0 25px rgba(0,0,0,0.4);
        border-right: 1px solid rgba(255,255,255,0.05);
    }
    
    .sidebar-brand { 
        padding: 20px 15px; 
        display: flex; 
        flex-direction: column; 
        justify-content: center; 
        align-items: center; 
        border-bottom: 1px solid rgba(255,255,255,0.08); 
        margin-bottom: 10px; 
    }
    
    .sidebar-brand-icon { 
        width: 110px; 
        height: 90px; 
        background: #ffffff; 
        border-radius: 14px; 
        overflow: hidden; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        box-shadow: 0 4px 12px rgba(0,0,0,0.2); 
        margin-bottom: 12px;
    }

    .sidebar-brand-icon img {
        width: 100%;
        height: 100%;
        object-fit: contain; 
        padding: 5px;
    }

    .sidebar-brand-text { 
        display: flex; 
        flex-direction: column;
        align-items: center;
    }

    .sidebar-brand-title { 
        color: #ffffff; 
        font-weight: 800; 
        font-size: 1.3rem; 
        letter-spacing: 1px;
        text-shadow: 0 2px 4px rgba(0,0,0,0.5);
    }

    .sidebar-user { 
        margin: 15px 16px 25px 16px; 
        padding: 14px; 
        background: linear-gradient(135deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.9));
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px; 
        display: flex; 
        align-items: center; 
        gap: 12px; 
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3), inset 0 1px 0 rgba(255,255,255,0.05);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .sidebar-user::before {
        content: '';
        position: absolute;
        top: -20px; left: -20px;
        width: 60px; height: 60px;
        background: <?php echo $color_rol; ?>;
        filter: blur(40px);
        opacity: 0.3;
        border-radius: 50%;
        z-index: 0;
    }

    .sidebar-user:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255,255,255,0.08);
    }

    .sidebar-user-avatar { 
        width: 40px; 
        height: 40px; 
        border-radius: 50%; 
        background: <?php echo $color_rol; ?>; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        color: white; 
        font-weight: 700; 
        font-size: 1rem; 
        flex-shrink: 0; 
        box-shadow: 0 0 0 3px rgba(255,255,255,0.1), 0 4px 12px rgba(0,0,0,0.3); 
        z-index: 1;
        position: relative;
    }

    .sidebar-user-info { 
        display: flex; 
        flex-direction: column; 
        line-height: 1.2; 
        z-index: 1;
        position: relative;
        width: 100%;
    }

    .sidebar-user-name { 
        color: #f8fafc; 
        font-weight: 700; 
        font-size: 0.85rem; 
        letter-spacing: 0.3px;
        white-space: normal;
        word-break: break-word;
    }

    .sidebar-user-role { 
        color: rgba(255,255,255,0.6); 
        font-size: 0.7rem; 
        font-weight: 600; 
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 4px;
    }

    .sidebar-section-title { color: rgba(255,255,255,0.35); font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; padding: 16px 24px 8px 24px; }
    .sidebar .nav { list-style: none; padding: 0 12px; margin: 0; flex-direction: column; display: flex; }
    .sidebar .nav-item { margin-bottom: 2px; }
    .sidebar .nav-link { color: rgba(255,255,255,0.7) !important; font-weight: 500; font-size: 0.9rem; padding: 12px 16px; border-radius: 12px; transition: all 0.25s ease; display: flex; align-items: center; gap: 14px; text-decoration: none; position: relative; }
    .sidebar .nav-link i { font-size: 1.15rem; width: 24px; text-align: center; color: rgba(255,255,255,0.5); transition: all 0.25s ease; flex-shrink: 0; }
    .sidebar .nav-link:hover { background: rgba(255,255,255,0.06); color: #ffffff !important; }
    .sidebar .nav-link:hover i { color: #6bc5ff; transform: scale(1.1); }
    .sidebar .nav-link.active { background: linear-gradient(90deg, <?php echo $color_rol; ?>22, <?php echo $color_rol; ?>11); color: #ffffff !important; border-left: 3px solid <?php echo $color_rol; ?>; padding-left: 13px; }
    .sidebar .nav-link.active i { color: <?php echo $color_rol; ?>; }

    .sidebar .dropdown-toggle::after { margin-left: auto; transition: transform 0.3s ease; font-size: 0.7rem; opacity: 0.5; }
    .sidebar .dropdown.show .dropdown-toggle::after { transform: rotate(180deg); }
    .sidebar .dropdown-menu { background: #1e293b !important; backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.08) !important; border-radius: 14px; padding: 8px; margin: 4px 0 4px 30px; box-shadow: 0 10px 40px rgba(0,0,0,0.5); min-width: 180px; }
    .sidebar .dropdown-menu .dropdown-item { color: #cbd5e1 !important; background: transparent !important; padding: 10px 14px; border-radius: 10px; transition: all 0.2s ease; display: flex; align-items: center; gap: 12px; font-weight: 500; font-size: 0.85rem; }
    .sidebar .dropdown-menu .dropdown-item i { font-size: 1rem; width: 20px; text-align: center; color: rgba(255,255,255,0.5) !important; }
    .sidebar .dropdown-menu .dropdown-item:hover { background: rgba(139,92,246,0.25) !important; color: #ffffff !important; transform: translateX(3px); }
    .sidebar .dropdown-menu .dropdown-item:hover i { color: #a78bfa !important; }
    .sidebar .dropdown-menu .dropdown-item.active { background: linear-gradient(135deg, #6d28d9, #8b5cf6) !important; color: #fff !important; }
    .sidebar .dropdown-menu .dropdown-item.active i { color: #fff !important; }

    .sidebar .nav-link.text-danger { color: #f87171 !important; margin-top: 8px; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 16px; border-radius: 0; }
    .sidebar .nav-link.text-danger:hover { background: rgba(239,68,68,0.1); color: #ef4444 !important; }
    .sidebar .nav-link.text-danger i { color: #f87171; }

    .main-content { margin-left: 260px; padding: 18px 20px; min-height: 100vh; transition: all 0.3s ease; background: #f8fafc; max-width: calc(100vw - 260px); overflow-x: hidden; box-sizing: border-box; }
    @media (max-width: 991px) {
        .sidebar { width: 220px; }
        .main-content { margin-left: 220px; padding: 14px; max-width: calc(100vw - 220px); }
        .sidebar .nav-link { font-size: 0.85rem; padding: 10px 14px; }
    }
    @media (max-width: 768px) {
        .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
        .sidebar.show { transform: translateX(0); }
        .main-content { margin-left: 0; padding: 14px; max-width: 100vw; }
    }
    .sidebar::-webkit-scrollbar { width: 5px; }
    .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.02); }
    .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 8px; }
    .sidebar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.25); }

    .row > [class^="col-"], .row > [class*=" col-"] { min-width: 0; }
</style>

<nav class="sidebar">

    <div class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <img src="<?php echo $base_url; ?>assets/imagen/FarmaTrace.jpeg" alt="FarmaTrace Logo">
        </div>
        <div class="sidebar-brand-text">
            <span class="sidebar-brand-title">FarmaTrace</span>
        </div>
    </div>

    <ul class="nav flex-column">

        <li class="nav-item">
            <a class="nav-link <?php echo $archivo_actual == 'dashboard.php' ? 'active' : ''; ?>"
               href="<?php echo $base_url; ?>/modules/<?php echo $tipo_usuario; ?>/dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>

        <?php if ($tipo_usuario == 'admin'): ?>
            <li class="sidebar-section-title">Personal</li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($archivo_actual, ['gestion_doctores.php','usuarios.php']) ? 'active' : ''; ?>"
                   href="<?php echo $base_url; ?>/modules/admin/usuarios.php">
                    <i class="fas fa-user-md"></i> Gestión de Doctores
                </a>
            </li>
            <li class="sidebar-section-title">Análisis</li>
            <li class="nav-item">
                <a class="nav-link <?php echo $archivo_actual == 'reportes_personal.php' ? 'active' : ''; ?>"
                   href="<?php echo $base_url; ?>/modules/admin/reportes_personal.php">
                    <i class="fas fa-chart-bar"></i> Reportes del Personal
                </a>
            </li>

        <?php elseif ($tipo_usuario == 'jefe_farmacia'): ?>
            <li class="sidebar-section-title">Administración</li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="bodegaDropdown" role="button" data-bs-toggle="dropdown">
                    <i class="fas fa-warehouse"></i> Bodega
                </a>
                <ul class="dropdown-menu" aria-labelledby="bodegaDropdown">
                    <li><a class="dropdown-item" href="<?php echo $base_url; ?>modules/jefe_farmacia/inventario.php"><i class="fas fa-chart-pie"></i> Inventario</a></li>
                    <li><a class="dropdown-item" href="<?php echo $base_url; ?>modules/jefe_farmacia/lotes.php"><i class="fas fa-boxes"></i> Lotes</a></li>
                </ul>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($archivo_actual, ['medicamentos.php','gestion_medicamentos.php']) ? 'active' : ''; ?>"
                   href="<?php echo $base_url; ?>/modules/jefe_farmacia/medicamentos.php">
                    <i class="fas fa-capsules"></i> Medicamentos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $archivo_actual == 'categorias.php' ? 'active' : ''; ?>"
                   href="<?php echo $base_url; ?>/modules/jefe_farmacia/categorias.php">
                    <i class="fas fa-tags"></i> Categorías
                </a>
            </li>
            <li class="sidebar-section-title">Solicitudes</li>
            <li class="nav-item">
                <a class="nav-link <?php echo $archivo_actual == 'solicitud_reposicion.php' ? 'active' : ''; ?>"
                   href="<?php echo $base_url; ?>/modules/jefe_farmacia/solicitud_reposicion.php">
                    <i class="fas fa-file-invoice"></i> Solicitud de Reposición
                </a>
            </li>
            <li class="sidebar-section-title">Análisis</li>
            <li class="nav-item">
                <a class="nav-link <?php echo $archivo_actual == 'reportes.php' ? 'active' : ''; ?>"
                   href="<?php echo $base_url; ?>/modules/jefe_farmacia/reportes.php">
                    <i class="fas fa-chart-bar"></i> Reportes
                </a>
            </li>

        <?php elseif ($tipo_usuario == 'doctor'): ?>
            <li class="sidebar-section-title">Recetas</li>
            <li class="nav-item">
                <a class="nav-link <?php echo $archivo_actual == 'crear_receta.php' ? 'active' : ''; ?>"
                   href="<?php echo $base_url; ?>/modules/doctor/crear_receta.php">
                    <i class="fas fa-file-prescription"></i> Crear Receta
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $archivo_actual == 'historial_recetas.php' ? 'active' : ''; ?>"
                   href="<?php echo $base_url; ?>modules/doctor/historial_recetas.php">
                    <i class="fas fa-history"></i> Historial de Recetas
                </a>
            </li>

        <?php elseif ($tipo_usuario == 'farmacia'): ?>
            <li class="sidebar-section-title">Atención</li>
            <li class="nav-item">
                <a class="nav-link <?php echo $archivo_actual == 'buscar_paciente.php' ? 'active' : ''; ?>"
                   href="<?php echo $base_url; ?>/modules/farmacia/buscar_paciente.php">
                    <i class="fas fa-search"></i> Buscar Paciente
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $archivo_actual == 'recetas_recibidas.php' ? 'active' : ''; ?>"
                   href="<?php echo $base_url; ?>/modules/farmacia/recetas_recibidas.php">
                    <i class="fas fa-prescription"></i> Recetas Recibidas
                </a>
            </li>
            <li class="sidebar-section-title">Entregas</li>
            <li class="nav-item">
                <a class="nav-link <?php echo $archivo_actual == 'entrega_medicamentos.php' ? 'active' : ''; ?>"
                   href="<?php echo $base_url; ?>/modules/farmacia/entrega_medicamentos.php">
                    <i class="fas fa-hand-holding-medical"></i> Entregas
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $archivo_actual == 'historial_entrega.php' ? 'active' : ''; ?>"
                   href="<?php echo $base_url; ?>modules/farmacia/historial_entrega.php">
                    <i class="fas fa-history"></i> Historial de Entregas
                </a>
            </li>
            <li class="sidebar-section-title">Análisis</li>
            <li class="nav-item">
                <a class="nav-link <?php echo $archivo_actual == 'reportes.php' ? 'active' : ''; ?>"
                   href="<?php echo $base_url; ?>modules/farmacia/reportes.php">
                    <i class="fas fa-chart-bar"></i> Reportes
                </a>
            </li>
        <?php endif; ?>

        <li class="nav-item">
            <a class="nav-link text-danger" href="<?php echo $base_url; ?>logout.php">
                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
            </a>
        </li>

    </ul>
    
    <div class="sidebar-user">
        <div class="sidebar-user-avatar"><?php echo strtoupper(substr($nombre_usuario, 0, 1)); ?></div>
        <div class="sidebar-user-info">
            <span class="sidebar-user-name"><?php echo htmlspecialchars($nombre_usuario); ?></span>
            <span class="sidebar-user-role"><?php echo $nombre_rol; ?></span>
        </div>
    </div>
    
</nav>

<main class="main-content">