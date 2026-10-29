<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/database.php';


if ($_SESSION['tipo_usuario'] != 'jefe_farmacia') {
    header("Location: ../../login.php");
    exit();
}
$titulo_pagina = "Bodega - Sistema de Inventario";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
// Determinar qué pestaña mostrar
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'inventario';
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
                            <h2 class="display-6 fw-bold mb-1">Bodega - Sistema de Inventario</h2>
                            <p class="opacity-75 mb-0">
                                <i class="fas fa-boxes me-2"></i>
                                Gestión completa de inventario y lotes de medicamentos
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <ul class="nav nav-pills mb-4 bg-white rounded-4 shadow-sm p-2" id="bodegaTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link <?php echo $tab == 'inventario' ? 'active' : ''; ?>" 
               href="bodega.php?tab=inventario" role="tab">
                <i class="fas fa-chart-pie me-2"></i> Resumen de Inventario
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?php echo $tab == 'lotes' ? 'active' : ''; ?>" 
               href="bodega.php?tab=lotes" role="tab">
                <i class="fas fa-boxes me-2"></i> Gestión de Lotes
            </a>
        </li>
    </ul>
    <div class="tab-content">
        <?php if ($tab == 'inventario'): ?>
            <?php include 'inventario.php'; ?>
        <?php else: ?>
            <?php include 'lotes.php'; ?>
        <?php endif; ?>
    </div>

</div>

<?php require_once '../../includes/footer.php'; ?>