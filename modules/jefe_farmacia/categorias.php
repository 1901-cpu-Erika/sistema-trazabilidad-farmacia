<?php
if (session_status() === PHP_SESSION_NONE) session_save_path('/tmp'); // Esto es clave para Vercel
   session_save_path('/tmp'); // Clave para Vercel
    session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'jefe_farmacia') {
    header("Location: /login.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    if ($nombre !== '') {
        $stmt = $conn->prepare("INSERT INTO categorias (nombre) VALUES (?)");
        $stmt->bind_param("s", $nombre);
        $stmt->execute();
        $stmt->close();
        header("Location: categorias.php?msg=creada");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
    $id     = (int)$_POST['id'];
    $nombre = trim($_POST['nombre'] ?? '');
    if ($id > 0 && $nombre !== '') {
        $stmt = $conn->prepare("UPDATE categorias SET nombre = ? WHERE id = ?");
        $stmt->bind_param("si", $nombre, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: categorias.php?msg=actualizada");
        exit();
    }
}

if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    $stmt = $conn->prepare("DELETE FROM categorias WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: categorias.php?msg=eliminada");
    exit();
}

// PAGINACIÓN DE 6 EN 6
$por_pagina = 6;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_actual - 1) * $por_pagina;

// Contar total de registros
$total_query = $conn->query("SELECT COUNT(*) as total FROM categorias");
$total_registros = $total_query->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $por_pagina);

// Obtener registros de la página actual
$categorias = $conn->query("
    SELECT c.id, c.nombre,
           (SELECT COUNT(*) FROM medicamentos m WHERE m.categoria_id = c.id) AS total_meds
    FROM categorias c
    ORDER BY c.nombre ASC
    LIMIT $por_pagina OFFSET $offset
");

$titulo_pagina = "Gestión de Categorías";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<style>
    :root {
        --cat-primary: #059669;
        --cat-primary-dark: #065f46;
        --cat-primary-light: #d1fae5;
        --cat-accent: #10b981;
    }
    .cat-hero {
        background: linear-gradient(135deg, var(--cat-primary-dark) 0%, var(--cat-primary) 55%, var(--cat-accent) 100%);
        border-radius: 22px;
        padding: 32px 34px;
        color: #fff;
        position: relative;
        overflow: hidden;
        box-shadow: 0 15px 40px rgba(5,150,105,0.28);
        margin-bottom: 26px;
    }
    .cat-hero::before {
        content: '\f02c';
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        position: absolute;
        right: 20px; bottom: -30px;
        font-size: 190px;
        opacity: 0.08;
        transform: rotate(-15deg);
    }
    .cat-hero-icon {
        background: rgba(255,255,255,0.22);
        width: 64px; height: 64px;
        border-radius: 18px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.7rem;
        backdrop-filter: blur(6px);
        flex-shrink: 0;
    }
    .cat-hero h3 { font-size: 1.7rem; letter-spacing: -0.3px; }
    .cat-hero p  { opacity: 0.9; }
    
    .btn-nueva-categoria {
        background: #fff;
        color: var(--cat-primary-dark);
        border-radius: 50px;
        padding: 12px 24px;
        font-weight: 700;
        border: none;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    .btn-nueva-categoria:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 30px rgba(0,0,0,0.2);
        background: #f8fafc;
    }
    .btn-nueva-categoria i {
        background: var(--cat-primary-light);
        color: var(--cat-primary-dark);
        padding: 6px;
        border-radius: 50%;
        margin-right: 8px;
    }

    .cat-alert {
        border: none;
        border-radius: 14px;
        padding: 14px 18px;
        display: flex; align-items: center; gap: 10px;
        background: linear-gradient(135deg,#ecfdf5,#f0fdf4);
        color: #065f46;
        box-shadow: 0 4px 14px rgba(5,150,105,0.08);
        font-weight: 500;
    }
    .cat-alert i { font-size: 1.2rem; color: var(--cat-primary); }
    .cat-card {
        background: #fff;
        border-radius: 18px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 6px 22px rgba(15,23,42,0.05);
        overflow: hidden;
        transition: all 0.3s;
    }
    .cat-card:hover { box-shadow: 0 12px 32px rgba(15,23,42,0.08); }

    .cat-card-header {
        padding: 18px 22px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid #f1f5f9;
        background: linear-gradient(180deg,#fafbfc,#f8fafc);
    }
    .cat-card-header h6 {
        margin: 0;
        font-weight: 700;
        color: var(--cat-primary-dark);
        font-size: 0.95rem;
        display: flex; align-items: center; gap: 8px;
    }
    .cat-card-header h6 i {
        width: 28px; height: 28px;
        display: flex; align-items: center; justify-content: center;
        background: var(--cat-primary-light);
        color: var(--cat-primary-dark);
        border-radius: 8px;
        font-size: 0.85rem;
    }
    
    .cat-table { margin: 0; font-size: 0.9rem; }
    .cat-table thead th {
        background: #f8fafc;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        font-weight: 700;
        color: #64748b;
        border-bottom: 1px solid #e2e8f0;
        padding: 14px 18px;
    }
    .cat-table tbody td {
        padding: 14px 18px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }
    .cat-table tbody tr { transition: all 0.18s; }
    .cat-table tbody tr:hover {
        background: linear-gradient(90deg, rgba(5,150,105,0.04), transparent);
        transform: scale(1.002);
    }

    .cat-id-badge {
        background: #ecfdf5;
        color: var(--cat-primary-dark);
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.78rem;
    }
    .cat-name { font-weight: 700; color: #0f172a; font-size: 1rem; }
    .cat-count {
        background: #dbeafe;
        color: #1e40af;
        padding: 5px 14px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.78rem;
        display: inline-flex; align-items: center; gap: 5px;
    }
    .cat-count.empty { background: #f1f5f9; color: #64748b; }
    .cat-action-btn {
        width: 34px; height: 34px;
        border-radius: 10px;
        border: none;
        display: inline-flex; align-items: center; justify-content: center;
        transition: all 0.2s;
        font-size: 0.85rem;
        cursor: pointer;
    }
    .cat-btn-edit {
        background: #fef3c7;
        color: #b45309;
    }
    .cat-btn-edit:hover { background: #fcd34d; transform: translateY(-2px); }

    .cat-btn-delete {
        background: #fee2e2;
        color: #b91c1c;
    }
    .cat-btn-delete:hover { background: #fecaca; transform: translateY(-2px); }
    
    .cat-empty {
        padding: 60px 20px;
        text-align: center;
        color: #94a3b8;
    }
    .cat-empty i {
        font-size: 3.5rem;
        color: #cbd5e1;
        margin-bottom: 14px;
        display: block;
    }
    .cat-empty h6 { color: #475569; font-weight: 700; margin-bottom: 6px; }

    .cat-search {
        position: relative;
        max-width: 260px;
    }
    .cat-search input {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        padding: 9px 14px 9px 38px;
        font-size: 0.85rem;
        width: 100%;
        transition: all 0.2s;
        background: #f8fafc;
    }
    .cat-search input:focus {
        border-color: var(--cat-primary);
        box-shadow: 0 0 0 4px rgba(5,150,105,0.12);
        background: #fff;
        outline: none;
    }
    .cat-search::before {
        content: '\f002';
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        position: absolute;
        left: 14px; top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 0.85rem;
    }

    .pagination { gap: 6px; }
    .page-item .page-link {
        border-radius: 50%;
        width: 38px;
        height: 38px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #e9ecef;
        color: var(--cat-primary-dark);
        font-weight: 700;
        transition: all 0.2s;
    }
    .page-item.active .page-link {
        background: linear-gradient(135deg, var(--cat-primary-dark), var(--cat-primary));
        color: white;
        border-color: transparent;
        box-shadow: 0 4px 10px rgba(5,150,105,0.3);
    }
    .page-item .page-link:hover {
        background: #f8f9fa;
        border-color: var(--cat-primary);
    }

    /* DISEÑO DE LA VENTANA MODAL */
    .modal-custom-content {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border-radius: 24px;
        border: 1px solid rgba(255,255,255,0.5);
        box-shadow: 0 25px 80px rgba(0,0,0,0.3);
        overflow: hidden;
    }
    .modal-custom-header {
        background: linear-gradient(135deg, var(--cat-primary-dark), var(--cat-primary));
        padding: 25px 30px;
        border: none;
        position: relative;
    }
    .modal-custom-header h5 { color: #fff; font-weight: 800; position: relative; z-index: 1; }
    .modal-custom-body { padding: 30px; }
    
    .floating-label { position: relative; margin-bottom: 0; }
    .floating-input {
        width: 100%;
        padding: 18px 20px 18px 50px;
        border-radius: 12px;
        border: 2px solid #e2e8f0;
        background: #f8fafc;
        font-size: 0.95rem;
        font-weight: 500;
        color: #0f172a;
        transition: all 0.3s ease;
    }
    .floating-input:focus {
        border-color: var(--cat-primary);
        background: #fff;
        box-shadow: 0 0 0 4px rgba(5,150,105,0.15);
        outline: none;
    }
    .floating-input::placeholder { color: transparent; }
    .floating-label label {
        position: absolute;
        top: 50%;
        left: 50px;
        transform: translateY(-50%);
        color: #94a3b8;
        font-weight: 600;
        pointer-events: none;
        transition: all 0.2s ease;
        background: transparent;
        padding: 0 5px;
    }
    .floating-input:focus ~ label,
    .floating-input:not(:placeholder-shown) ~ label {
        top: 0;
        left: 40px;
        font-size: 0.75rem;
        color: var(--cat-primary);
        background: #fff;
        padding: 0 8px;
        transform: translateY(-50%) scale(1);
    }
    .input-icon {
        position: absolute;
        left: 18px; top: 50%;
        transform: translateY(-50%);
        color: var(--cat-primary);
        font-size: 1.1rem;
        pointer-events: none;
    }
    
    .modal-custom-footer {
        padding: 20px 30px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        border-radius: 0 0 24px 24px;
    }

    .fade-in { animation: fadeUp 0.5s ease forwards; }
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="container-fluid px-4 fade-in">
    <div class="cat-hero d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center">
            <div class="cat-hero-icon me-3">
                <i class="fas fa-tags"></i>
            </div>
            <div>
                <h3 class="fw-bold mb-1">Gestión de Categorías</h3>
                <p class="mb-0">Organiza los medicamentos por tipo y clasificación</p>
            </div>
        </div>
        <button type="button" id="btnNuevaCategoria" class="btn-nueva-categoria">
            <i class="fas fa-plus"></i> Nueva Categoría
        </button>
    </div>
    
    <?php if (isset($_GET['msg'])): ?>
        <div class="cat-alert mb-4">
            <i class="fas fa-check-circle"></i>
            Categoría <strong class="ms-1"><?php echo htmlspecialchars($_GET['msg']); ?></strong> correctamente.
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <div class="cat-card">
                <div class="cat-card-header">
                    <h6><i class="fas fa-list"></i> Categorías Existentes</h6>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="cat-search">
                            <input type="text" id="searchCat" placeholder="Buscar categoría...">
                        </div>
                        <span class="badge rounded-pill" style="background: var(--cat-primary); padding: 7px 14px;">
                            <?php echo $total_registros; ?> total
                        </span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table cat-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">ID</th>
                                    <th>Nombre de la Categoría</th>
                                    <th class="text-center" style="width: 150px;">Medicamentos</th>
                                    <th class="text-end" style="width: 120px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tablaCategorias">
                            <?php if ($categorias && $categorias->num_rows > 0): ?>
                                <?php while ($cat = $categorias->fetch_assoc()):
                                    $total = (int)$cat['total_meds'];
                                ?>
                                    <tr data-nombre="<?php echo htmlspecialchars(strtolower($cat['nombre'])); ?>">
                                        <td><span class="cat-id-badge">#<?php echo $cat['id']; ?></span></td>
                                        <td>
                                            <div class="cat-name"><?php echo htmlspecialchars($cat['nombre']); ?></div>
                                        </td>
                                        <td class="text-center">
                                            <span class="cat-count <?php echo $total === 0 ? 'empty' : ''; ?>">
                                                <i class="fas fa-capsules"></i> <?php echo $total; ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="cat-action-btn cat-btn-edit me-1"
                                                    title="Editar"
                                                    onclick="editarCategoria(
                                                        <?php echo $cat['id']; ?>,
                                                        '<?php echo addslashes(htmlspecialchars($cat['nombre'])); ?>'
                                                    )">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            <button type="button" class="cat-action-btn cat-btn-delete"
                                                    title="Eliminar"
                                                    onclick="confirmarEliminar(<?php echo $cat['id']; ?>, '<?php echo addslashes(htmlspecialchars($cat['nombre'])); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="cat-empty">
                                            <i class="fas fa-tags"></i>
                                            <h6>No hay categorías registradas</h6>
                                            <p class="mb-0 small">Crea tu primera categoría usando el botón de arriba</p>
                                        </div>
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
    </div>
</div>

<div class="modal fade" id="modalCrearCategoria" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-custom-content">
            <div class="modal-header modal-custom-header">
                <h5 class="modal-title"><i class="fas fa-tags me-2"></i> Nueva Categoría</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body modal-custom-body">
                    <div class="floating-label">
                        <i class="fas fa-tag input-icon"></i>
                        <input type="text" name="nombre" id="nuevoNombre" class="floating-input" placeholder=" " required>
                        <label for="nuevoNombre">Nombre de la categoría</label>
                    </div>
                </div>
                <div class="modal-footer modal-custom-footer">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="crear" class="btn btn-success rounded-pill px-4 fw-bold" style="background: var(--cat-primary); border: none; box-shadow: 0 4px 15px rgba(5,150,105,0.3);">
                        <i class="fas fa-check me-1"></i> Guardar Categoría
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<form method="POST" id="formEditar" style="display:none;">
    <input type="hidden" name="editar" value="1">
    <input type="hidden" name="id" id="editId">
</form>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const btnNueva = document.getElementById('btnNuevaCategoria');
        const modalElement = document.getElementById('modalCrearCategoria');
        
        if (btnNueva && modalElement) {
            btnNueva.addEventListener('click', function() {
                let myModal = bootstrap.Modal.getInstance(modalElement);
                if (!myModal) {
                    myModal = new bootstrap.Modal(modalElement);
                }
                myModal.show();
            });
        }
    });

    document.getElementById('searchCat').addEventListener('input', function (e) {
        const q = e.target.value.toLowerCase().trim();
        document.querySelectorAll('#tablaCategorias tr[data-nombre]').forEach(tr => {
            tr.style.display = tr.dataset.nombre.includes(q) ? '' : 'none';
        });
    });
    function editarCategoria(id, nombre) {
        Swal.fire({
            title: 'Editar Categoría',
            html: `
                <div style="text-align:left;">
                    <label style="font-size:0.82rem;font-weight:600;color:#475569;">Nombre</label>
                    <input id="swalNombre" class="swal2-input" value="${nombre}" style="margin:5px 0 0;width:100%;">
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-save"></i> Guardar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#059669',
            cancelButtonColor: '#94a3b8',
            focusConfirm: false,
            preConfirm: () => {
                const n = document.getElementById('swalNombre').value.trim();
                if (!n) { Swal.showValidationMessage('El nombre es obligatorio'); return false; }
                return { nombre: n };
            }
        }).then(result => {
            if (result.isConfirmed) {
                document.getElementById('editId').value = id;
                const form = document.getElementById('formEditar');
                const inputN = document.createElement('input');
                inputN.type = 'hidden'; inputN.name = 'nombre'; inputN.value = result.value.nombre;
                form.appendChild(inputN);
                form.submit();
            }
        });
    }
    function confirmarEliminar(id, nombre) {
        Swal.fire({
            title: '¿Eliminar categoría?',
            html: `Vas a eliminar <strong>${nombre}</strong>.<br>Esta acción no se puede deshacer.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-trash"></i> Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#94a3b8',
        }).then(result => {
            if (result.isConfirmed) {
                window.location.href = 'categorias.php?eliminar=' + id;
            }
        });
    }
</script>

<?php require_once '../../includes/footer.php'; ?>