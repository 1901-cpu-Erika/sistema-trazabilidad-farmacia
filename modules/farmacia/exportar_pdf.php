<?php
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');

$sql = "
SELECT
    r.id,
    CONCAT(p.nombre,' ',p.apellido) AS paciente,
    u.nombre AS doctor,
    r.fecha_receta,
    r.estado
FROM recetas r
INNER JOIN pacientes p ON r.paciente_id = p.id
INNER JOIN usuarios u ON r.doctor_id = u.id
WHERE r.fecha_receta BETWEEN ? AND ?
ORDER BY r.fecha_receta DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
$stmt->execute();
$result = $stmt->get_result();


$html = '
<!DOCTYPE html>
<html>
<head>
<style>
    @page {
        margin: 100px 50px 50px 50px;
    }
    
    body {
        font-family: "Helvetica", sans-serif;
        font-size: 11px;
        color: #333;
    }
    
    /* Encabezado fijo */
    .header {
        position: fixed;
        top: -80px;
        left: 0;
        right: 0;
        height: 70px;
        background: linear-gradient(135deg, #1a3a5c, #2d6a9f);
        color: white;
        text-align: center;
        padding: 15px;
        border-radius: 8px;
        z-index: 1000;
    }
    
    .header h1 {
        margin: 0;
        font-size: 18px;
        letter-spacing: 1px;
    }
    
    .header p {
        margin: 5px 0 0 0;
        font-size: 12px;
        opacity: 0.9;
    }
    
    /* Pie de página fijo */
    .footer {
        position: fixed;
        bottom: -30px;
        left: 0;
        right: 0;
        height: 25px;
        font-size: 9px;
        text-align: center;
        color: #777;
        border-top: 1px solid #ddd;
        padding-top: 8px;
    }
    
    /* Contenido del reporte */
    .report-info {
        background: #f8f9fa;
        border-left: 5px solid #2d6a9f;
        padding: 12px 15px;
        margin-bottom: 25px;
        border-radius: 0 8px 8px 0;
        font-size: 12px;
    }
    
    .report-info b {
        color: #1a3a5c;
    }
    
    /* Tabla */
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    
    thead {
        background: #2d6a9f;
        color: white;
    }
    
    thead th {
        padding: 10px 8px;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border: none;
    }
    
    tbody tr:nth-child(even) {
        background: #f2f7fb;
    }
    
    tbody tr:hover {
        background: #e2edf5;
    }
    
    tbody td {
        padding: 9px 8px;
        border-bottom: 1px solid #e0e0e0;
        text-align: center;
    }
    
    .estado {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 9px;
        font-weight: bold;
        text-transform: uppercase;
        color: white;
    }
    
    .estado-pendiente {
        background: #f59e0b;
    }
    
    .estado-aprobada {
        background: #10b981;
    }
    
    .estado-entregada {
        background: #3b82f6;
    }
    
    .estado-rechazada {
        background: #ef4444;
    }
    
    .total-row {
        margin-top: 20px;
        font-size: 13px;
        font-weight: bold;
        text-align: right;
        color: #1a3a5c;
    }
</style>
</head>
<body>
    <div class="header">
        <h1>FARMACIA Y TRAZABILIDAD</h1>
        <p>Sistema de Control de Recetas Médicas | Reporte General</p>
    </div>
    <div class="footer">
        Generado el ' . date('d/m/Y H:i') . ' | Página {PAGE_NUM} de {PAGE_COUNT}
    </div>
    <div class="report-info">
        <h3 style="margin-top: 0; color: #1a3a5c;">Reporte de Recetas Médicas</h3>
        <p>
            <b>Desde:</b> ' . date('d/m/Y', strtotime($fecha_inicio)) . '<br>
            <b>Hasta:</b> ' . date('d/m/Y', strtotime($fecha_fin)) . '
        </p>
    </div>
    <table>
        <thead>
            <tr>
                <th style="width: 8%;">ID</th>
                <th style="width: 30%;">Paciente</th>
                <th style="width: 25%;">Doctor</th>
                <th style="width: 17%;">Fecha</th>
                <th style="width: 20%;">Estado</th>
            </tr>
        </thead>
        <tbody>
';


function colorEstado($estado) {
    return match($estado) {
        'pendiente' => 'estado-pendiente',
        'aprobada' => 'estado-aprobada',
        'entregada' => 'estado-entregada',
        'rechazada' => 'estado-rechazada',
        default => 'estado-pendiente'
    };
}

$contador = 0;
while($row = $result->fetch_assoc()){
    $contador++;
    $html .= '
            <tr>
                <td>#' . $row['id'] . '</td>
                <td style="text-align: left;"><b>' . htmlspecialchars($row['paciente']) . '</b></td>
                <td style="text-align: left;">' . htmlspecialchars($row['doctor']) . '</td>
                <td>' . date('d/m/Y', strtotime($row['fecha_receta'])) . '</td>
                <td><span class="estado ' . colorEstado($row['estado']) . '">' . ucfirst($row['estado']) . '</span></td>
            </tr>
    ';
}

$html .= '
        </tbody>
    </table>

    <!-- RESUMEN FINAL -->
    <div class="total-row">
        Total de recetas encontradas: ' . $contador . '
    </div>

</body>
</html>
';
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);

$dompdf->setPaper('A4', 'portrait');

$dompdf->render();

$dompdf->stream(
    "Reporte_Recetas_" . date('Y-m-d') . ".pdf",
    ["Attachment" => true]
);