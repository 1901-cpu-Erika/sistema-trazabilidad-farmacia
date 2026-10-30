<?php
// 1. Arreglo de Sesión para Vercel
if (session_status() === PHP_SESSION_NONE) {
    session_save_path('/tmp');
    session_start();
}

require_once '../../config/database.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] != 'jefe_farmacia') {
    header("Location: /login.php");
    exit();
}

// Validar que vengan los datos
if (!isset($_POST['datos_pdf'])) {
    die("Error: No se recibieron los datos de la solicitud. Por favor, regrese a la pantalla anterior y genere el PDF desde los botones.");
}

$datos_solicitud = json_decode($_POST['datos_pdf'], true);

$establecimiento = $_SESSION['establecimiento'] ?? 'Centro de Salud';
$responsable     = $_SESSION['usuario_nombre']   ?? 'Jefe de Farmacia';
$fecha           = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Formato 6 - Solicitud de Medicamentos</title>
    <style>
        /* ESTILOS DE PANTALLA */
        body { font-family: Arial, sans-serif; background: #f1f5f9; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 40px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        
        /* ESTILOS DEL PDF (Impresión) */
        @media print {
            body { background: white; padding: 0; }
            .container { box-shadow: none; padding: 0; max-width: 100%; }
            .no-print { display: none !important; }
            @page { margin: 20px; size: A4 portrait; }
        }

        .header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #065f46; padding-bottom: 15px; }
        .header h2 { margin: 2px 0; font-size: 14px; color: #065f46; }
        .header h3 { margin: 2px 0; font-size: 13px; font-weight: normal; }
        .header h4 { margin: 8px 0 0; font-size: 12px; color: #065f46; }
        
        .info { margin-bottom: 15px; }
        .info p { margin: 3px 0; font-size: 12px; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        th { background: #065f46; color: white; padding: 10px 8px; text-align: left; font-size: 11px; }
        td { padding: 8px; border-bottom: 1px solid #e5e7eb; font-size: 11px; }
        tr:nth-child(even) { background: #f8fafc; }
        
        .firmas { margin-top: 50px; page-break-inside: avoid; }
        .firmas table { border: none; }
        .firmas td { border: none; text-align: center; width: 33%; padding-top: 50px; font-size: 11px; }
        .firma-linea { border-top: 1px solid #000; margin: 0 10px 5px; padding-top: 40px; }
        
        .footer { text-align: center; font-size: 9px; color: #64748b; margin-top: 30px; }

        /* BOTÓN FLOTANTE */
        .btn-print {
            position: fixed; bottom: 30px; right: 30px; background: #065f46; color: white;
            padding: 15px 25px; border-radius: 50px; border: none; font-size: 16px; font-weight: bold;
            cursor: pointer; box-shadow: 0 4px 15px rgba(6, 95, 70, 0.4); transition: all 0.3s;
            display: flex; align-items: center; gap: 10px;
        }
        .btn-print:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(6, 95, 70, 0.6); }
    </style>
</head>
<body>

<!-- BOTÓN PARA GUARDAR COMO PDF -->
<button class="btn-print no-print" onclick="window.print()">
    🖨️ Guardar como PDF
</button>

<div class="container">
    <div class="header">
        <h2>REPÚBLICA DE NICARAGUA</h2>
        <h2>MINISTERIO DE SALUD (MINSA)</h2>
        <h3>SISTEMA LOCAL DE ATENCIÓN INTEGRAL EN SALUD (SILAIS)</h3>
        <h4>FORMATO 6 - SOLICITUD DE MEDICAMENTOS</h4>
    </div>

    <div class="info">
        <p><strong>Establecimiento:</strong> <?php echo htmlspecialchars($establecimiento); ?></p>
        <p><strong>Servicio:</strong> Farmacia / Sub-bodega</p>
        <p><strong>Fecha de solicitud:</strong> <?php echo $fecha; ?></p>
        <p><strong>Responsable:</strong> <?php echo htmlspecialchars($responsable); ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 30px;">No.</th>
                <th style="width: 80px;">Código</th>
                <th>Descripción del Insumo</th>
                <th style="width: 55px;">UM</th>
                <th style="width: 60px; text-align:center;">Existencia</th>
                <th style="width: 70px; text-align:center;">Cant. Solicitada</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if (is_array($datos_solicitud) && count($datos_solicitud) > 0) {
                $i = 1;
                foreach ($datos_solicitud as $item) {
                    $id_med = $item['id'];
                    $nombre = $item['nombre'];
                    $solicitado = $item['cantidad']; 
                    
                    $stmt = $conn->prepare("SELECT m.codigo_minsa, COALESCE(SUM(l.cantidad), 0) AS stock_actual 
                                            FROM medicamentos m 
                                            LEFT JOIN lotes l ON m.id = l.medicamento_id AND l.fecha_vencimiento > CURDATE() 
                                            WHERE m.id = ? AND m.estado = 1 
                                            GROUP BY m.id");
                    $stmt->bind_param("i", $id_med);
                    $stmt->execute();
                    $res = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    $codigo = $res['codigo_minsa'] ?? '—';
                    $stock  = $res['stock_actual'] ?? 0;
            ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td><?php echo htmlspecialchars($codigo); ?></td>
                    <td><?php echo htmlspecialchars($nombre); ?></td>
                    <td>Unidad</td>
                    <td style="text-align:center;"><?php echo $stock; ?></td>
                    <td style="text-align:center;"><strong><?php echo $solicitado; ?></strong></td>
                </tr>
            <?php 
                }
            } else {
                echo '<tr><td colspan="6" style="text-align:center; padding: 20px;">No se seleccionaron medicamentos.</td></tr>';
            }
            ?>
        </tbody>
    </table>

    <p style="font-size: 10px; color: #64748b; margin-top: -10px;">
        <em>Nota: Esta solicitud se realiza en base a las necesidades del establecimiento y el stock actual.</em>
    </p>

    <div class="firmas">
        <table>
            <tr>
                <td>
                    <div class="firma-linea"></div>
                    <strong>Elaborado por</strong><br>
                    <?php echo htmlspecialchars($responsable); ?><br>
                    Jefe de Farmacia
                </td>
                <td>
                    <div class="firma-linea"></div>
                    <strong>Revisado por</strong><br>
                    Dirección del Centro<br>
                    &nbsp;
                </td>
                <td>
                    <div class="firma-linea"></div>
                    <strong>Recibido por</strong><br>
                    SILAIS / Almacén<br>
                    &nbsp;
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Generado por Sistema de Trazabilidad de Farmacia - <?php echo $fecha; ?>
    </div>
</div>

</body>
</html>