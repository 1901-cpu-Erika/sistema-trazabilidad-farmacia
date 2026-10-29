<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] != 'jefe_farmacia') {
    header("Location: ../../login.php");
    exit();
}

$autoload = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoload)) {
    die('<h2 style="font-family:sans-serif;color:#b91c1c;">Error: Dompdf no está instalado.</h2>
         <p style="font-family:sans-serif;">Ejecuta en la terminal:</p>
         <pre style="background:#f1f5f9;padding:12px;border-radius:8px;">cd C:\\xampp\\htdocs\\sistema-trazabilidad-farmacia
composer require dompdf/dompdf</pre>');
}
require_once $autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_POST['datos_pdf'])) {
    die("Error: No se recibieron los datos de la solicitud. Por favor, regrese a la pantalla anterior y genere el PDF desde los botones.");
}

$datos_solicitud = json_decode($_POST['datos_pdf'], true);

$establecimiento = $_SESSION['establecimiento'] ?? 'Centro de Salud';
$responsable     = $_SESSION['usuario_nombre']   ?? 'Jefe de Farmacia';
$fecha           = date('d/m/Y');

$html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 25px; color: #1e293b; }
        .header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #065f46; padding-bottom: 15px; }
        .header h2 { margin: 2px 0; font-size: 14px; color: #065f46; }
        .header h3 { margin: 2px 0; font-size: 13px; font-weight: normal; }
        .header h4 { margin: 8px 0 0; font-size: 12px; color: #065f46; }
        .info { margin-bottom: 15px; }
        .info p { margin: 3px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        th { background: #065f46; color: white; padding: 8px 6px; text-align: left; font-size: 10px; }
        td { padding: 7px 6px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
        tr:nth-child(even) { background: #f8fafc; }
        .firmas { margin-top: 50px; }
        .firmas table { border: none; }
        .firmas td { border: none; text-align: center; width: 33%; padding-top: 50px; font-size: 10px; }
        .firma-linea { border-top: 1px solid #000; margin: 0 10px 5px; padding-top: 40px; }
        .footer { position: fixed; bottom: 10px; left: 0; right: 0; text-align: center; font-size: 9px; color: #64748b; }
    </style>
</head>
<body>
    <div class="header">
        <h2>REPÚBLICA DE NICARAGUA</h2>
        <h2>MINISTERIO DE SALUD (MINSA)</h2>
        <h3>SISTEMA LOCAL DE ATENCIÓN INTEGRAL EN SALUD (SILAIS)</h3>
        <h4>FORMATO 6 - SOLICITUD DE MEDICAMENTOS</h4>
    </div>

    <div class="info">
        <p><strong>Establecimiento:</strong> ' . htmlspecialchars($establecimiento) . '</p>
        <p><strong>Servicio:</strong> Farmacia / Sub-bodega</p>
        <p><strong>Fecha de solicitud:</strong> ' . $fecha . '</p>
        <p><strong>Responsable:</strong> ' . htmlspecialchars($responsable) . '</p>
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
        <tbody>';
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

        $html .= '
            <tr>
                <td>' . $i++ . '</td>
                <td>' . htmlspecialchars($codigo) . '</td>
                <td>' . htmlspecialchars($nombre) . '</td>
                <td>Unidad</td>
                <td style="text-align:center;">' . $stock . '</td>
                <td style="text-align:center;"><strong>' . $solicitado . '</strong></td>
            </tr>';
    }
} else {
    $html .= '<tr><td colspan="6" style="text-align:center; padding: 20px;">No se seleccionaron medicamentos.</td></tr>';
}

$html .= '
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
                    ' . htmlspecialchars($responsable) . '<br>
                    Jefe de Farmacia
                </td>
                <td>
                    <div class="firma-linea"></div>
                    <strong>Revisado por</strong><br>
                    Dirección del Centro<br>
                </td>
                <td>
                    <div class="firma-linea"></div>
                    <strong>Recibido por</strong><br>
                    SILAIS / Almacén<br>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Generado por Sistema de Trazabilidad de Farmacia - ' . $fecha . '
    </div>
</body>
</html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('Formato6_Solicitud_' . date('Ymd_His') . '.pdf', array('Attachment' => false));
?>