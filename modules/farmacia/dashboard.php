<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'farmacia') {
    header("Location: ../../login.php");
    exit();
}

$titulo_pagina = "Dashboard - Farmacia";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
$stats = [];

$stats['recetas_recibidas'] = (int)$conn->query("
    SELECT COUNT(*) AS t FROM recetas WHERE estado = 'aprobada'
")->fetch_assoc()['t'];

$stats['entregas_hoy'] = (int)$conn->query("
    SELECT COUNT(*) AS t FROM entregas WHERE DATE(fecha_entrega) = CURDATE()
")->fetch_assoc()['t'];

$stats['pendientes'] = (int)$conn->query("
    SELECT COUNT(*) AS t FROM recetas r
    WHERE r.estado = 'aprobada'
    AND NOT EXISTS (SELECT 1 FROM entregas e WHERE e.receta_id = r.id)
")->fetch_assoc()['t'];

$stats['stock_bajo'] = (int)($conn->query("
    SELECT SUM(cantidad) AS t FROM lotes WHERE cantidad < 10
")->fetch_assoc()['t'] ?? 0);

$por_pagina = 6;
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina - 1) * $por_pagina;

$sql_total = "SELECT COUNT(*) as total FROM recetas WHERE estado IN ('aprobada','pendiente','entregada')";
$total_recetas = $conn->query($sql_total)->fetch_assoc()['total'];
$total_paginas = ceil($total_recetas / $por_pagina);

$sql = "SELECT r.id, r.fecha_receta, r.estado,
               p.nombre AS paciente_nombre, p.apellido AS paciente_apellido,
               u.nombre AS doctor_nombre
        FROM recetas r
        JOIN pacientes p ON r.paciente_id = p.id
        JOIN usuarios  u ON r.doctor_id   = u.id
        WHERE r.estado IN ('aprobada','pendiente','entregada')
        ORDER BY r.fecha_receta DESC
        LIMIT $por_pagina OFFSET $offset";
$recetas_recientes = $conn->query($sql);

$sql_stock = "SELECT m.nombre, l.cantidad, l.fecha_vencimiento
              FROM lotes l
              JOIN medicamentos m ON l.medicamento_id = m.id
              WHERE l.cantidad < 10
              ORDER BY l.cantidad ASC";
$stock_bajo_lista = $conn->query($sql_stock);
?>

<style>

    .main-content { padding-top: 0 !important; }
    .dashboard-container { padding: 0 20px 20px 20px; margin-top: -10px; }
    .stat-card {
        border: none;
        border-radius: 16px;
        color: white;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
        position: relative;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.15);
    }
    .stat-card.clickable { cursor: pointer; }
    .stat-card.clickable:hover { filter: brightness(1.05); }

    .card-purple { background: linear-gradient(135deg, #4c1d95 0%, #7c3aed 100%); }
    .card-teal   { background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%); }
    .card-amber  { background: linear-gradient(135deg, #b45309 0%, #f59e0b 100%); }
    .card-red    { background: linear-gradient(135deg, #9f1239 0%, #ef4444 100%); }

    .stat-icon {
        position: absolute;
        right: 15px;
        bottom: 10px;
        font-size: 80px;
        opacity: 0.15;
    }
    .card-content { position: relative; z-index: 1; }
    .custom-card {
        border: none;
        border-radius: 16px;
        box-shadow: 0 6px 22px rgba(15,23,42,0.06);
        background: #fff;
        overflow: hidden;
    }
    .custom-card-header {
        background: linear-gradient(180deg, #fafbfc, #f8fafc);
        border-bottom: 2px solid #f1f5f9;
        padding: 18px 25px;
        font-weight: 700;
        font-size: 1.05rem;
        color: #0f172a;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .table thead th {
        font-size: 0.75rem;
        text-transform: uppercase;
        color: #64748b;
        border-bottom: 2px solid #e2e8f0;
        padding: 14px 18px;
        background: #f8fafc;
    }
    .table tbody td {
        padding: 14px 18px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }
    .table tbody tr { transition: all 0.2s; }
    .table tbody tr:hover {
        background: linear-gradient(90deg, rgba(124,58,237,0.03), transparent);
        transform: scale(1.002);
    }

    .btn-gradient-purple { background: linear-gradient(135deg, #4c1d95, #7c3aed); color: #fff; border: none; box-shadow: 0 4px 12px rgba(124,58,237,0.3); }
    .btn-gradient-purple:hover { color: #fff; transform: translateY(-2px); box-shadow: 0 6px 18px rgba(124,58,237,0.4); }
    .btn-gradient-amber { background: linear-gradient(135deg, #b45309, #f59e0b); color: #fff; border: none; box-shadow: 0 4px 12px rgba(245,158,11,0.3); }
    .btn-gradient-amber:hover { color: #fff; transform: translateY(-2px); box-shadow: 0 6px 18px rgba(245,158,11,0.4); }
    .badge-soft-warning   { background: #fff3cd; color: #856404; padding: 6px 12px; border-radius: 8px; font-weight: 600; }
    .badge-soft-success   { background: #d4edda; color: #155724; padding: 6px 12px; border-radius: 8px; font-weight: 600; }
    .badge-soft-secondary { background: #e2e3e5; color: #383d41; padding: 6px 12px; border-radius: 8px; font-weight: 600; }
    .badge-soft-info      { background: #cff4fc; color: #055160; padding: 6px 12px; border-radius: 8px; font-weight: 600; }

    /* PAGINACIÓN */
    .pagination { gap: 6px; }
    .page-item .page-link {
        border-radius: 50%;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #e9ecef;
        color: #4c1d95;
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

    .fade-in { animation: fadeUp 0.6s ease forwards; }
    @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="container-fluid px-4 mt-3 fade-in">
    <div class="card border-0 shadow-lg rounded-4 overflow-hidden position-relative mb-4"
         style="background: linear-gradient(135deg, #4c1d95 0%, #7c3aed 100%);">
        <i class="fas fa-pills position-absolute" style="font-size: 150px; right: 20px; top: -30px; opacity: 0.1; color: white;"></i>
        <i class="fas fa-hand-holding-medical position-absolute" style="font-size: 100px; right: 180px; bottom: -30px; opacity: 0.1; color: white;"></i>

        <div class="card-body p-4 text-white position-relative">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-white bg-opacity-25 p-3 me-3 shadow">
                    <i class="fas fa-user-nurse fa-2x text-white"></i>
                </div>
                <div>
                    <h2 class="display-6 fw-bold mb-1" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.3);">
                        ¡Bienvenido/a, <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Personal'); ?>!
                    </h2>
                    <p class="mb-0 opacity-75">
                        <i class="fas fa-clinic-medical me-2"></i>
                        Centro de Atención y Entrega de Medicamentos
                    </p>
                </div>
            </div>
        </div>
    </div>
    <div class="dashboard-container">
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card card-purple h-100">
                    <div class="card-body">
                        <div class="card-content">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-white-50 mb-1 text-uppercase fw-bold small">Recetas Recibidas</p>
                                    <h2 class="fw-bold mb-0 text-white"><?php echo $stats['recetas_recibidas']; ?></h2>
                                </div>
                                <div class="bg-white bg-opacity-25 rounded-circle p-3 d-flex align-items-center justify-content-center">
                                    <i class="fas fa-prescription fa-lg text-white"></i>
                                </div>
                            </div>
                        </div>
                        <i class="fas fa-prescription stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card card-teal h-100">
                    <div class="card-body">
                        <div class="card-content">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-white-50 mb-1 text-uppercase fw-bold small">Entregas Hoy</p>
                                    <h2 class="fw-bold mb-0 text-white"><?php echo $stats['entregas_hoy']; ?></h2>
                                </div>
                                <div class="bg-white bg-opacity-25 rounded-circle p-3 d-flex align-items-center justify-content-center">
                                    <i class="fas fa-truck fa-lg text-white"></i>
                                </div>
                            </div>
                        </div>
                        <i class="fas fa-truck stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card card-amber h-100">
                    <div class="card-body">
                        <div class="card-content">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-white-50 mb-1 text-uppercase fw-bold small">Pendientes</p>
                                    <h2 class="fw-bold mb-0 text-white"><?php echo $stats['pendientes']; ?></h2>
                                </div>
                                <div class="bg-white bg-opacity-25 rounded-circle p-3 d-flex align-items-center justify-content-center">
                                    <i class="fas fa-clock fa-lg text-white"></i>
                                </div>
                            </div>
                        </div>
                        <i class="fas fa-clock stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card card-red h-100 clickable" data-bs-toggle="modal" data-bs-target="#modalStockBajo">
                    <div class="card-body">
                        <div class="card-content">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-white-50 mb-1 text-uppercase fw-bold small">Stock Bajo</p>
                                    <h2 class="fw-bold mb-0 text-white"><?php echo $stats['stock_bajo']; ?> <small class="fs-6">uds.</small></h2>
                                    <?php if($stats['stock_bajo'] > 0): ?>
                                        <span class="badge bg-white text-danger mt-2 px-2 py-1" style="font-size: 0.65rem;"><i class="fas fa-exclamation-triangle"></i> Clic para ver</span>
                                    <?php endif; ?>
                                </div>
                                <div class="bg-white bg-opacity-25 rounded-circle p-3 d-flex align-items-center justify-content-center">
                                    <i class="fas fa-box-open fa-lg text-white"></i>
                                </div>
                            </div>
                        </div>
                        <i class="fas fa-box-open stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-4 mb-4">
            <div class="col-xl-12 col-lg-12">
                <div class="card custom-card h-100">
                    <div class="card-header custom-card-header">
                        <span><i class="fas fa-file-prescription text-primary me-2"></i>Recetas Recientes</span>
                        <a href="recetas_recibidas.php" class="btn btn-gradient-purple btn-sm rounded-pill px-4">Ver todas</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Paciente</th>
                                        <th>Doctor</th>
                                        <th>Fecha</th>
                                        <th>Estado</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recetas_recientes && $recetas_recientes->num_rows > 0): ?>
                                        <?php while ($receta = $recetas_recientes->fetch_assoc()): 
                                            $stmt_meds = $conn->prepare("
                                                SELECT m.nombre, rd.dosis, rd.cantidad 
                                                FROM receta_detalles rd 
                                                JOIN medicamentos m ON rd.medicamento_id = m.id 
                                                WHERE rd.receta_id = ?
                                            ");
                                            $stmt_meds->bind_param("i", $receta['id']);
                                            $stmt_meds->execute();
                                            $res_meds = $stmt_meds->get_result();
                                            $meds_list = [];
                                            while($m = $res_meds->fetch_assoc()) {
                                                $meds_list[] = $m;
                                            }
                                            $stmt_meds->close();
                                            $meds_json = htmlspecialchars(json_encode($meds_list), ENT_QUOTES, 'UTF-8');
                                        ?>
                                        <tr>
                                            <td class="ps-4 fw-bold text-dark">
                                                <?php echo htmlspecialchars($receta['paciente_nombre'] . ' ' . $receta['paciente_apellido']); ?>
                                            </td>
                                            <td>
                                                <span class="text-muted small">
                                                    <i class="fas fa-user-md me-1 text-secondary"></i>
                                                    <?php echo htmlspecialchars($receta['doctor_nombre']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($receta['fecha_receta'])); ?></td>
                                            <td>
                                                <?php
                                                $estado = strtolower($receta['estado']);
                                                if ($estado == 'pendiente') {
                                                    $badge = 'badge-soft-warning'; $icon = 'fa-clock';
                                                } elseif ($estado == 'aprobada') {
                                                    $badge = 'badge-soft-info';    $icon = 'fa-check-circle';
                                                } elseif ($estado == 'entregada') {
                                                    $badge = 'badge-soft-success'; $icon = 'fa-truck';
                                                } else {
                                                    $badge = 'badge-soft-secondary'; $icon = 'fa-circle';
                                                }
                                                ?>
                                                <span class="badge <?php echo $badge; ?>">
                                                    <i class="fas <?php echo $icon; ?> me-1"></i>
                                                    <?php echo ucfirst($estado); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-primary rounded-circle"
                                                        onclick="verDetalleReceta(
                                                            '<?php echo addslashes(htmlspecialchars($receta['paciente_nombre'] . ' ' . $receta['paciente_apellido'])); ?>',
                                                            '<?php echo addslashes(htmlspecialchars($receta['doctor_nombre'])); ?>',
                                                            '<?php echo date('d/m/Y', strtotime($receta['fecha_receta'])); ?>',
                                                            '<?php echo ucfirst($estado); ?>',
                                                            '<?php echo $meds_json; ?>'
                                                        )"
                                                        data-bs-toggle="modal" data-bs-target="#modalDetalleReceta">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-5">
                                                <i class="fas fa-inbox fa-3x d-block mb-3 opacity-50"></i>
                                                <h6 class="fw-bold">No hay recetas recientes</h6>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php if ($total_paginas > 1): ?>
                    <div class="card-footer bg-white border-0 py-3">
                        <nav>
                            <ul class="pagination justify-content-center mb-0">
                                <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?pagina=<?php echo $pagina - 1; ?>"><i class="fas fa-chevron-left"></i></a>
                                </li>
                                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                    <li class="page-item <?php echo $pagina == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $pagina >= $total_paginas ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?pagina=<?php echo $pagina + 1; ?>"><i class="fas fa-chevron-right"></i></a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="modalStockBajo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header border-0" style="background: linear-gradient(135deg, #9f1239 0%, #ef4444 100%); padding: 20px 25px;">
                <h5 class="modal-title text-white fw-bold"><i class="fas fa-exclamation-triangle me-2"></i> Alertas de Stock Bajo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Medicamento</th>
                            <th class="text-center">Stock Actual</th>
                            <th>Fecha Vencimiento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($stock_bajo_lista->num_rows > 0): ?>
                            <?php while($med = $stock_bajo_lista->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark"><i class="fas fa-capsules text-danger me-2"></i><?php echo htmlspecialchars($med['nombre']); ?></td>
                                    <td class="text-center"><span class="badge bg-danger rounded-pill px-3 py-2 fs-6"><?php echo $med['cantidad']; ?> uds</span></td>
                                    <td><?php echo date('d/m/Y', strtotime($med['fecha_vencimiento'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center py-4 text-muted">No hay productos con stock bajo.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer border-0 bg-light justify-content-center">
                <button type="button" class="btn btn-secondary rounded-pill px-5" data-bs-dismiss="modal">
                    <i class="fas fa-check me-1"></i> Entendido
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="modalDetalleReceta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header border-0" style="background: linear-gradient(135deg, #4c1d95 0%, #7c3aed 100%); padding: 20px 25px;">
                <h5 class="modal-title text-white fw-bold"><i class="fas fa-file-prescription me-2"></i> Detalle de la Receta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <small class="text-muted fw-bold text-uppercase d-block mb-1">Paciente</small>
                        <strong id="modalPacienteNombre" class="fs-5 text-dark"></strong>
                    </div>
                    <div class="col-md-6 mb-3">
                        <small class="text-muted fw-bold text-uppercase d-block mb-1">Doctor</small>
                        <strong id="modalDoctorNombre" class="fs-6 text-dark"></strong>
                    </div>
                    <div class="col-md-6 mb-3">
                        <small class="text-muted fw-bold text-uppercase d-block mb-1">Fecha de Emisión</small>
                        <strong id="modalFecha" class="text-dark"></strong>
                    </div>
                    <div class="col-md-6 mb-3">
                        <small class="text-muted fw-bold text-uppercase d-block mb-1">Estado</small>
                        <div id="modalEstado"></div>
                    </div>
                </div>
                
                <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fas fa-capsules text-primary me-2"></i>Medicamentos Recetados</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Medicamento</th>
                                <th>Dosis / Indicación</th>
                                <th class="text-center">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody id="modalTablaMedicamentos">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    function verDetalleReceta(paciente, doctor, fecha, estado, medsJson) {
        // 1. Poner los datos básicos
        document.getElementById('modalPacienteNombre').innerText = paciente;
        document.getElementById('modalDoctorNombre').innerText = doctor;
        document.getElementById('modalFecha').innerText = fecha;
        let badgeClass = 'badge-soft-info';
        let icon = 'fa-check-circle';
        if(estado === 'Entregada') { badgeClass = 'badge-soft-success'; icon = 'fa-truck'; }
        else if(estado === 'Pendiente') { badgeClass = 'badge-soft-warning'; icon = 'fa-clock'; }
        
        document.getElementById('modalEstado').innerHTML = `<span class="badge ${badgeClass} fs-6"><i class="fas ${icon} me-1"></i>${estado}</span>`;
        let meds = JSON.parse(medsJson);
        let tbody = document.getElementById('modalTablaMedicamentos');
        tbody.innerHTML = '';
        
        if(meds.length > 0) {
            meds.forEach(m => {
                tbody.innerHTML += `
                    <tr>
                        <td class="ps-4 fw-bold text-dark"><i class="fas fa-pills text-primary me-2"></i>${m.nombre}</td>
                        <td><span class="badge bg-light text-dark border">${m.dosis || 'No especificada'}</span></td>
                        <td class="text-center"><span class="badge bg-primary rounded-pill px-3">${m.cantidad}</span></td>
                    </tr>
                `;
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="3" class="text-center py-4 text-muted"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>No hay medicamentos en esta receta.</td></tr>`;
        }
    }
</script>

<?php require_once '../../includes/footer.php'; ?>