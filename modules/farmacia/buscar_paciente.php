<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';


if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] != 'farmacia') {
    header("Location: /login.php");
    exit();
}


$titulo_pagina = "Buscar Paciente";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$farmacia_id = $_SESSION['usuario_id'];


$totalPacientes = 0;
$totalConRecetas = 0;
$totalMenores = 0;


$result = $conn->query("SELECT COUNT(*) AS total FROM pacientes");
if ($result && $result->num_rows > 0) {
    $totalPacientes = $result->fetch_assoc()['total'] ?? 0;
}

$result = $conn->query("SELECT COUNT(DISTINCT paciente_id) AS total 
                        FROM recetas 
                        WHERE estado = 'aprobada'");
if ($result && $result->num_rows > 0) {
    $totalConRecetas = $result->fetch_assoc()['total'] ?? 0;
}


$result = $conn->query("SELECT COUNT(*) AS total FROM pacientes WHERE edad < 18");
if ($result && $result->num_rows > 0) {
    $totalMenores = $result->fetch_assoc()['total'] ?? 0;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$term = "%$search%";

// PAGINACIÓN DE 6 EN 6
$por_pagina = 6;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_actual - 1) * $por_pagina;

// Contar total de registros para la paginación
$stmtCount = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM pacientes 
    WHERE nombre LIKE ? OR apellido LIKE ? OR cedula LIKE ? OR telefono LIKE ?
");
$total_registros = 0;
if ($stmtCount) {
    $stmtCount->bind_param("ssss", $term, $term, $term, $term);
    $stmtCount->execute();
    $countResult = $stmtCount->get_result();
    if ($countResult && $countResult->num_rows > 0) {
        $total_registros = $countResult->fetch_assoc()['total'] ?? 0;
    }
    $stmtCount->close();
}
$total_paginas = ceil($total_registros / $por_pagina);
?>

<style>
    /* SUBIR EL CONTENIDO */
    .main-content { padding-top: 0 !important; }
    .container-fluid.px-4 { margin-top: -10px; }

    .avatar-circle {
        width: 42px; height: 42px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 700; font-size: 15px;
        flex-shrink: 0; box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    .table tbody tr { transition: all 0.15s ease; border-bottom: 1px solid rgba(0,0,0,0.03); }
    .table tbody tr:hover { background: rgba(124, 58, 237, 0.04); }
    .fade-in { animation: fadeUp 0.6s ease forwards; }
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .bg-warning-subtle { background: #fff3cd !important; color: #856404 !important; }
    .bg-success-subtle { background: #d4edda !important; color: #155724 !important; }

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
</style>

<div class="container-fluid px-4 fade-in">

    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden" style="background: linear-gradient(135deg, #4c1d95 0%, #7c3aed 50%, #a78bfa 100%);">
                <div class="card-body p-4 text-white">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-white bg-opacity-20 p-3 me-3 shadow">
                                    <i class="fas fa-search fa-2x text-white"></i>
                                </div>
                                <div>
                                    <h2 class="display-6 fw-bold mb-1" style="text-shadow: 0 2px 8px rgba(0,0,0,0.3);">
                                        <i class="fas fa-user-injured me-2"></i> Buscar Paciente
                                    </h2>
                                    <p class="opacity-90 mb-0">
                                        <i class="fas fa-pills me-2"></i> Personal de Farmacia
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    <div class="row g-3 mb-4">
        <div class="col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-top: 5px solid #7c3aed;">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle p-3 me-3" style="background: rgba(124, 58, 237, 0.12);">
                        <i class="fas fa-users fa-2x" style="color: #7c3aed;"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0" style="color: #4c1d95; font-size: 2rem;"><?php echo $totalPacientes; ?></h5>
                        <small class="text-muted">Pacientes totales</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-top: 5px solid #2A9DBF;">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle p-3 me-3" style="background: rgba(42, 157, 191, 0.12);">
                        <i class="fas fa-prescription fa-2x" style="color: #2A9DBF;"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0" style="color: #4c1d95; font-size: 2rem;"><?php echo $totalConRecetas; ?></h5>
                        <small class="text-muted">Con recetas aprobadas</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-top: 5px solid #F5A623;">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle p-3 me-3" style="background: rgba(245, 166, 35, 0.12);">
                        <i class="fas fa-child fa-2x" style="color: #F5A623;"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0" style="color: #4c1d95; font-size: 2rem;"><?php echo $totalMenores; ?></h5>
                        <small class="text-muted">Menores de edad</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    <div class="card border-0 shadow-lg rounded-4" style="background: rgba(255,255,255,0.95); border: 2px solid #7c3aed;">
        <div class="card-body p-4">

            <form method="GET" class="mb-4">
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white border-end-0 rounded-start-pill" style="border-color: #7c3aed;">
                        <i class="fas fa-search" style="color: #7c3aed;"></i>
                    </span>
                    <input type="text" name="search" class="form-control border-start-0"
                           style="border-color: #7c3aed;"
                           placeholder="Buscar por nombre, apellido, cédula o teléfono..."
                           value="<?php echo htmlspecialchars($search); ?>"
                           id="searchInput">
                    <button class="btn rounded-end-pill px-4" type="submit"
                            style="background: linear-gradient(135deg, #4c1d95 0%, #7c3aed 100%); color: #fff; border: none; font-weight: 600;">
                        <i class="fas fa-search me-1"></i> Buscar
                    </button>
                </div>
            </form>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <span class="badge rounded-pill px-4 py-2" style="background: #7c3aed; color: #fff; font-size: 0.9rem;">
                        <i class="fas fa-database me-1"></i>
                        <?php echo $total_registros . ' paciente' . ($total_registros != 1 ? 's' : '') . ' encontrado' . ($total_registros != 1 ? 's' : ''); ?>
                    </span>
                </div>
                <?php if (!empty($search)): ?>
                    <a href="buscar_paciente.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                        <i class="fas fa-undo-alt me-1"></i> Limpiar
                    </a>
                <?php endif; ?>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead style="background: #F3E8FF;">
                        <tr>
                            <th class="ps-3" style="color: #4c1d95; font-weight: 700;">ID</th>
                            <th style="color: #4c1d95; font-weight: 700;">Paciente</th>
                            <th style="color: #4c1d95; font-weight: 700;">Edad</th>
                            <th style="color: #4c1d95; font-weight: 700;">Contacto</th>
                            <th style="color: #4c1d95; font-weight: 700;">Recetas</th>
                            <th class="text-end pe-3" style="color: #4c1d95; font-weight: 700;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "
                            SELECT p.*,
                                   (SELECT COUNT(*) FROM recetas r WHERE r.paciente_id = p.id AND r.estado = 'aprobada') AS recetas_aprobadas,
                                   (SELECT COUNT(*) FROM recetas r WHERE r.paciente_id = p.id) AS total_recetas
                            FROM pacientes p
                            WHERE p.nombre LIKE ? OR p.apellido LIKE ? OR p.cedula LIKE ? OR p.telefono LIKE ?
                            ORDER BY p.nombre ASC
                            LIMIT ? OFFSET ?
                        ";
                        $stmt = $conn->prepare($query);
                        if ($stmt) {
                            $stmt->bind_param("ssssii", $term, $term, $term, $term, $por_pagina, $offset);
                            $stmt->execute();
                            $result = $stmt->get_result();

                            if ($result && $result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    $paciente_id = intval($row['id']);
                                    $nombre_completo = $row['nombre'] . ' ' . $row['apellido'];
                                    $edad = intval($row['edad']);
                                    $aprobadas = intval($row['recetas_aprobadas']);
                                    $total_rec = intval($row['total_recetas']);
                        ?>
                                    <tr>
                                        <td class="ps-3">
                                            <span class="badge rounded-pill" style="background: #7c3aed; color: #fff;">#<?php echo $paciente_id; ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle me-2" style="background: <?php echo '#' . substr(md5($nombre_completo), 0, 6); ?>;">
                                                    <span><?php echo strtoupper(substr($row['nombre'], 0, 1) . substr($row['apellido'], 0, 1)); ?></span>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($nombre_completo); ?></div>
                                                    <small class="text-muted">
                                                        <i class="fas fa-id-card me-1"></i>
                                                        <?php echo !empty($row['cedula']) ? htmlspecialchars($row['cedula']) : 'Sin cédula'; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="fw-bold fs-5" style="color: #4c1d95;"><?php echo $edad; ?></span>
                                            <small class="text-muted d-block">años</small>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['telefono'])): ?>
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-phone text-primary me-2"></i>
                                                    <span><?php echo htmlspecialchars($row['telefono']); ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">No registrado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($aprobadas > 0): ?>
                                                <span class="badge bg-success-subtle text-success-emphasis rounded-pill px-3 py-2">
                                                    <i class="fas fa-check-circle me-1"></i> <?php echo $aprobadas; ?> aprobada(s)
                                                </span>
                                            <?php elseif ($total_rec > 0): ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-3 py-2">
                                                    <i class="fas fa-clock me-1"></i> <?php echo $total_rec; ?> pendiente(s)
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill px-3 py-2">
                                                    <i class="fas fa-minus-circle me-1"></i> Sin recetas
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-3">
                                            <!-- DEJAMOS SOLO EL BOTÓN DE VER RECETAS, MÁS ANCHO Y ESTILIZADO -->
                                            <a href="recetas_recibidas.php?paciente_id=<?php echo $paciente_id; ?>"
                                               class="btn btn-sm rounded-pill px-4 shadow-sm"
                                               style="background: linear-gradient(135deg, #4c1d95 0%, #7c3aed 100%); color: #fff; border: none; font-weight: 600;">
                                                <i class="fas fa-prescription me-1"></i> Ver recetas
                                            </a>
                                        </td>
                                    </tr>
                        <?php
                                }
                            } else {
                                echo '<tr><td colspan="6" class="text-center py-5">';
                                echo '<i class="fas fa-search fa-3x text-muted mb-3 d-block"></i>';
                                echo '<h5 class="text-muted">No se encontraron pacientes</h5>';
                                echo '<p class="text-muted small">Intenta con otro término de búsqueda.</p>';
                                echo '</td></tr>';
                            }
                            $stmt->close();
                        } else {
                            echo '<tr><td colspan="6" class="text-center text-danger py-4">Error: ' . $conn->error . '</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINACIÓN -->
            <?php if ($total_paginas > 1): ?>
                <div class="mt-4">
                    <nav>
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?php echo $pagina_actual <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?search=<?php echo urlencode($search); ?>&pagina=<?php echo $pagina_actual - 1; ?>"><i class="fas fa-chevron-left"></i></a>
                            </li>
                            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                <li class="page-item <?php echo $pagina_actual == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?search=<?php echo urlencode($search); ?>&pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $pagina_actual >= $total_paginas ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?search=<?php echo urlencode($search); ?>&pagina=<?php echo $pagina_actual + 1; ?>"><i class="fas fa-chevron-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('searchInput');
        if (input) input.focus();
    });
</script>

<?php require_once '../../includes/footer.php'; ?>