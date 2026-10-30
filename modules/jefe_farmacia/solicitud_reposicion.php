<?php
if (session_status() === PHP_SESSION_NONE) session_save_path('/tmp'); // Clave para Vercel
    session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] != 'jefe_farmacia') {
    header("Location: /login.php");
    exit();
}
$limite_stock = isset($_GET['limite']) ? intval($_GET['limite']) : 20;

$sql = "SELECT 
            m.id,
            m.nombre,
            m.codigo_minsa,
            COALESCE(SUM(l.cantidad), 0) AS stock_actual,
            COUNT(l.id) AS lotes_activos
        FROM medicamentos m
        LEFT JOIN lotes l ON m.id = l.medicamento_id 
            AND l.fecha_vencimiento > CURDATE()
        WHERE m.estado = 1
        GROUP BY m.id, m.nombre, m.codigo_minsa
        HAVING stock_actual < ?
        ORDER BY stock_actual ASC, m.nombre ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $limite_stock);
$stmt->execute();
$medicamentos_bajos = $stmt->get_result();
$stmt->close();

$establecimiento = $_SESSION['establecimiento'] ?? 'Centro de Salud';
$responsable = $_SESSION['usuario_nombre'] ?? 'Jefe de Farmacia';
$fecha_solicitud = date('d/m/Y');

$titulo_pagina = "Solicitud de Reposición";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<style>
    .sol-hero { background: linear-gradient(135deg, #065f46 0%, #059669 55%, #10b981 100%); border-radius: 22px; padding: 30px 34px; color: #fff; position: relative; overflow: hidden; box-shadow: 0 15px 40px rgba(5,150,105,0.28); margin-bottom: 26px; }
    .sol-hero::before { content: '\f56e'; font-family: "Font Awesome 6 Free"; font-weight: 900; position: absolute; right: 20px; bottom: -30px; font-size: 180px; opacity: 0.1; }
    .sol-hero h3 { font-size: 1.6rem; font-weight: 700; margin-bottom: 4px; }
    .sol-hero p  { margin: 0; opacity: 0.9; }
    .sol-card { background: #fff; border-radius: 18px; border: 1px solid #e5e7eb; box-shadow: 0 6px 22px rgba(15,23,42,0.05); overflow: hidden; margin-bottom: 24px; }
    .sol-card-header { padding: 18px 22px; background: linear-gradient(180deg,#fafbfc,#f8fafc); border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
    .sol-card-header h6 { margin: 0; font-weight: 700; color: #065f46; font-size: 0.95rem; display: flex; align-items: center; gap: 8px; }
    .sol-card-header h6 i { width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; background: #d1fae5; color: #065f46; border-radius: 8px; font-size: 0.85rem; }
    .sol-table { margin: 0; font-size: 0.87rem; }
    .sol-table thead th { background: #f8fafc; font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: #64748b; padding: 12px 16px; border-bottom: 1px solid #e2e8f0; }
    .sol-table tbody td { padding: 12px 16px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
    .sol-table tbody tr:hover { background: linear-gradient(90deg, rgba(5,150,105,0.04), transparent); }
    .badge-critico { background: #fee2e2; color: #991b1b; padding: 5px 12px; border-radius: 20px; font-weight: 700; font-size: 0.75rem; }
    .badge-bajo { background: #fef3c7; color: #92400e; padding: 5px 12px; border-radius: 20px; font-weight: 700; font-size: 0.75rem; }
    .progress-stock { height: 6px; border-radius: 10px; background: #e2e8f0; margin-top: 5px; }
    .progress-stock .progress-bar { border-radius: 10px; }
    
    .input-solicitado { width: 100px; margin: 0 auto; text-align: center; font-weight: 700; color: #065f46; background-color: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 6px; font-size: 1rem; }
    .input-solicitado:focus { background-color: #fff; border-color: #059669; box-shadow: 0 0 0 3px rgba(5,150,105,0.15); outline: none; }
    .input-solicitado::placeholder { color: #94a3b8; font-weight: 400; }

    .btn-accion { border-radius: 12px; padding: 12px 22px; font-weight: 600; border: none; transition: all 0.25s; display: inline-flex; align-items: center; gap: 8px; }
    .btn-pdf { background: linear-gradient(135deg, #dc2626, #ef4444); color: #fff; }
    .btn-pdf:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(220,38,38,0.35); color: #fff; }
    .btn-mail { background: linear-gradient(135deg, #1e40af, #3b82f6); color: #fff; }
    .btn-mail:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(30,64,175,0.35); color: #fff; }
    .btn-wa { background: linear-gradient(135deg, #047857, #10b981); color: #fff; }
    .btn-wa:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(4,120,87,0.35); color: #fff; }
    .fade-in { animation: fadeUp 0.5s ease forwards; }
    @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="container-fluid px-4 fade-in">
    <div class="sol-hero d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <h3><i class="fas fa-file-invoice me-2"></i>Solicitud de Reposición de Medicamentos</h3>
            <p>Formato 6 - MINSA Nicaragua</p>
        </div>
        <a href="dashboard.php" class="btn btn-light rounded-pill px-4">
            <i class="fas fa-arrow-left me-1"></i> Dashboard
        </a>
    </div>
    
    <div class="sol-card">
        <div class="sol-card-header">
            <h6><i class="fas fa-filter"></i> Filtro de Stock Bajo</h6>
        </div>
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label class="form-label small fw-semibold text-muted">
                        Mostrar medicamentos con stock menor a:
                    </label>
                    <select name="limite" class="form-select rounded-pill" onchange="this.form.submit()">
                        <option value="10" <?php echo $limite_stock == 10 ? 'selected' : ''; ?>>10 unidades (Crítico)</option>
                        <option value="20" <?php echo $limite_stock == 20 ? 'selected' : ''; ?>>20 unidades (Bajo)</option>
                        <option value="30" <?php echo $limite_stock == 30 ? 'selected' : ''; ?>>30 unidades (Precaución)</option>
                        <option value="50" <?php echo $limite_stock == 50 ? 'selected' : ''; ?>>50 unidades</option>
                        <option value="100" <?php echo $limite_stock == 100 ? 'selected' : ''; ?>>100 unidades</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <span class="badge rounded-pill w-100 py-3" style="background:#059669;">
                        <?php echo $medicamentos_bajos ? $medicamentos_bajos->num_rows : 0; ?> medicamentos por debajo del límite
                    </span>
                </div>
            </form>
        </div>
    </div>

    <div class="sol-card">
        <div class="sol-card-header">
            <h6><i class="fas fa-capsules"></i> Medicamentos que Requieren Reposición</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table sol-table mb-0">
                    <thead>
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th style="width: 100px;">Código</th>
                            <th>Medicamento</th>
                            <th style="width: 100px;">UM</th>
                            <th style="width: 150px;" class="text-center">Stock Actual</th>
                            <th style="width: 150px;" class="text-center">Cantidad a Pedir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($medicamentos_bajos && $medicamentos_bajos->num_rows > 0): ?>
                            <?php $i = 1; while ($med = $medicamentos_bajos->fetch_assoc()):
                                $stock = (int)$med['stock_actual'];
                                $porcentaje = ($limite_stock > 0) ? ($stock / $limite_stock) * 100 : 0;
                                
                                if ($stock == 0) { $clase = 'badge-critico'; $bg_color = 'bg-danger'; } 
                                elseif ($porcentaje <= 30) { $clase = 'badge-critico'; $bg_color = 'bg-danger'; } 
                                elseif ($porcentaje <= 60) { $clase = 'badge-bajo'; $bg_color = 'bg-warning'; } 
                                else { $clase = 'badge-bajo'; $bg_color = 'bg-success'; }
                            ?>
                                <tr>
                                    <td class="text-muted"><?php echo $i++; ?></td>
                                    <td><code><?php echo htmlspecialchars($med['codigo_minsa'] ?: '—'); ?></code></td>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($med['nombre']); ?></td>
                                    <td>Unidad</td>
                                    <td class="text-center">
                                        <span class="<?php echo $clase; ?>"><?php echo $stock; ?></span>
                                        <div class="progress progress-stock">
                                            <div class="progress-bar <?php echo $bg_color; ?>" role="progressbar" style="width: <?php echo min($porcentaje, 100); ?>%"></div>
                                        </div>
                                    </td>
                                    
                                    <!-- AQUÍ ESTÁ LA CORRECCIÓN PARA EVITAR NEGATIVOS -->
                                    <td class="text-center">
                                        <input type="number" 
                                               class="input-solicitado cantidad-input" 
                                               value="" 
                                               placeholder="0"
                                               min="1" 
                                               step="1"
                                               data-id="<?php echo $med['id']; ?>"
                                               data-nombre="<?php echo htmlspecialchars($med['nombre']); ?>"
                                               onkeydown="if(event.key === '-' || event.key === 'e') event.preventDefault();"
                                               oninput="if(this.value < 0) this.value = Math.abs(this.value);"
                                               title="Escribe la cantidad exacta que necesitas pedir">
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fas fa-check-circle fa-3x mb-3 d-block text-success"></i>
                                    <h6>No hay medicamentos con stock bajo</h6>
                                    <p class="mb-0 small">Todos los medicamentos están por encima del límite establecido.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($medicamentos_bajos && $medicamentos_bajos->num_rows > 0): ?>
    <div class="sol-card">
        <div class="sol-card-header">
            <h6><i class="fas fa-paper-plane"></i> Enviar Solicitud</h6>
        </div>
        <div class="card-body p-4">
            <p class="text-muted small mb-3">
                <i class="fas fa-info-circle me-1"></i>
                <strong>Escribe la cantidad exacta a pedir</strong> en la columna verde. El sistema no pondrá límites; tú decides cuánto solicitar según la necesidad del centro.
            </p>
            <div class="d-flex flex-wrap gap-3">
                <button class="btn btn-accion btn-pdf" onclick="generarPDF()">
                    <i class="fas fa-file-pdf"></i> Descargar Formato 6 (PDF)
                </button>
                <button class="btn btn-accion btn-mail" onclick="enviarCorreo()">
                    <i class="fas fa-envelope"></i> Enviar por Correo
                </button>
                <button class="btn btn-accion btn-wa" onclick="enviarWhatsApp()">
                    <i class="fab fa-whatsapp"></i> Avisar por WhatsApp
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function obtenerDatosTabla() {
    const filas = document.querySelectorAll('tbody tr');
    let datos = [];
    
    filas.forEach(fila => {
        const input = fila.querySelector('.cantidad-input');
        if (input) {
            const id = input.getAttribute('data-id');
            const nombre = input.getAttribute('data-nombre');
            
            // AQUÍ TAMBIÉN SE REFUERZA LA VALIDACIÓN: Convertir a entero
            let cantidad = parseInt(input.value, 10);
            
            if (!isNaN(cantidad) && cantidad > 0) {
                datos.push({ id: id, nombre: nombre, cantidad: cantidad });
            }
        }
    });
    return datos;
}

function generarPDF() {
    const datos = obtenerDatosTabla();
    if (datos.length === 0) {
        Swal.fire('Atención', 'No has ingresado ninguna cantidad válida a pedir. Escribe números mayores a 0 en la columna verde.', 'warning');
        return;
    }
    
    Swal.fire({
        title: 'Generando Formato 6',
        text: 'Se abrirá el PDF con las cantidades que editaste',
        icon: 'info',
        timer: 2000,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    }).then(() => {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'generar_pdf_solicitud.php';
        form.target = '_blank';

        const inputData = document.createElement('input');
        inputData.type = 'hidden';
        inputData.name = 'datos_pdf';
        inputData.value = JSON.stringify(datos);
        form.appendChild(inputData);

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    });
}

function enviarCorreo() {
    const datos = obtenerDatosTabla();
    if (datos.length === 0) {
        Swal.fire('Atención', 'No has ingresado ninguna cantidad válida a pedir.', 'warning');
        return;
    }

    Swal.fire({
        title: 'Enviar por Correo',
        html: `
            <div style="text-align:left;">
                <label style="font-size:0.85rem;font-weight:600;color:#475569;">Correo del SILAIS / Dirección:</label>
                <input id="emailDestino" class="swal2-input" placeholder="" style="width:100%;">
                <label style="font-size:0.85rem;font-weight:600;color:#475569;margin-top:10px;">Asunto:</label>
                <input id="emailAsunto" class="swal2-input" value="Solicitud de Reposición de Medicamentos - <?php echo date('d/m/Y'); ?>" style="width:100%;">
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-paper-plane"></i> Enviar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#1e40af',
        cancelButtonColor: '#94a3b8',
        preConfirm: () => {
            const email = document.getElementById('emailDestino').value.trim();
            const asunto = document.getElementById('emailAsunto').value.trim();
            if (!email) { Swal.showValidationMessage('Ingresa el correo destino'); return false; }
            return { email, asunto };
        }
    }).then(result => {
        if (result.isConfirmed) {
            const subject = encodeURIComponent(result.value.asunto);
            const body = encodeURIComponent(
                'Estimado/a,\n\n' +
                'Adjunto encontrará la solicitud de reposición de medicamentos según Formato 6.\n\n' +
                'Saludos cordiales,\n' +
                '<?php echo $responsable; ?>\n' +
                '<?php echo $establecimiento; ?>'
            );
            window.location.href = 'mailto:' + result.value.email + '?subject=' + subject + '&body=' + body;
            Swal.fire('Listo', 'Se abrirá tu cliente de correo.', 'success');
        }
    });
}

function enviarWhatsApp() {
    const datos = obtenerDatosTabla();
    if (datos.length === 0) {
        Swal.fire('Atención', 'No has ingresado ninguna cantidad válida a pedir.', 'warning');
        return;
    }

    Swal.fire({
        title: 'Avisar por WhatsApp',
        html: `
            <div style="text-align:left;">
                <label style="font-size:0.85rem;font-weight:600;color:#475569;">Número de destino (con código 505):</label>
                <input id="waNumero" class="swal2-input" placeholder="" style="width:100%;">
                <small style="color:#64748b;font-size:0.75rem;"> </small>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fab fa-whatsapp"></i> Abrir WhatsApp',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#047857',
        cancelButtonColor: '#94a3b8',
        preConfirm: () => {
            const numero = document.getElementById('waNumero').value.trim().replace(/\D/g, '');
            if (!numero) { Swal.showValidationMessage('Ingresa el número'); return false; }
            return { numero };
        }
    }).then(result => {
        if (result.isConfirmed) {
            let listaMeds = '';
            datos.forEach((d, index) => {
                listaMeds += `${index + 1}. ${d.nombre} - Solicitado: ${d.cantidad} unidades\n`;
            });

            const mensaje = encodeURIComponent(
                '*SOLICITUD DE REPOSICIÓN DE MEDICAMENTOS*\n' +
                '📅 Fecha: <?php echo $fecha_solicitud; ?>\n' +
                '🏥 Establecimiento: <?php echo $establecimiento; ?>\n' +
                '👤 Elaborado por: <?php echo $responsable; ?>\n\n' +
                '📋 *Medicamentos solicitados:*\n' +
                listaMeds
            );
            window.open('https://wa.me/' + result.value.numero + '?text=' + mensaje, '_blank');
        }
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>