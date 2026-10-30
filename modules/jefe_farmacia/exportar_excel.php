<?php
if (session_status() === PHP_SESSION_NONE) session_save_path('/tmp'); // Esto es clave para Vercel
   session_save_path('/tmp'); // Clave para Vercel
    session_start();
date_default_timezone_set('America/Managua');
require_once '../../config/database.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] != 'jefe_farmacia') {
    header("Location: ../../login.php");
    exit();
}

$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin    = $_GET['fecha_fin']    ?? date('Y-m-d');
$total_recetas  = $conn->query("SELECT COUNT(*) t FROM recetas WHERE fecha_receta BETWEEN '$fecha_inicio' AND '$fecha_fin'")->fetch_assoc()['t'];
$total_unidades = $conn->query("SELECT COALESCE(SUM(rd.cantidad),0) t FROM receta_detalles rd JOIN recetas r ON rd.receta_id = r.id WHERE r.fecha_receta BETWEEN '$fecha_inicio' AND '$fecha_fin'")->fetch_assoc()['t'];

$sql = "SELECT estado, COUNT(*) total FROM recetas WHERE fecha_receta BETWEEN ? AND ? GROUP BY estado";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
$stmt->execute();
$estados = $stmt->get_result();
$stmt->close();

$sql = "SELECT m.nombre, SUM(rd.cantidad) total 
        FROM receta_detalles rd 
        JOIN medicamentos m ON rd.medicamento_id = m.id 
        JOIN recetas r ON rd.receta_id = r.id 
        WHERE r.fecha_receta BETWEEN ? AND ? 
        GROUP BY m.id, m.nombre 
        ORDER BY total DESC LIMIT 20";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
$stmt->execute();
$top_meds = $stmt->get_result();
$stmt->close();

$sql = "SELECT u.nombre, COUNT(r.id) total 
        FROM recetas r 
        JOIN usuarios u ON r.doctor_id = u.id 
        WHERE r.fecha_receta BETWEEN ? AND ? 
        GROUP BY u.id, u.nombre 
        ORDER BY total DESC LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
$stmt->execute();
$top_doctores = $stmt->get_result();
$stmt->close();

$stock_critico = $conn->query("
    SELECT m.nombre, COALESCE(SUM(l.cantidad), 0) AS stock
    FROM medicamentos m
    LEFT JOIN lotes l ON m.id = l.medicamento_id
    GROUP BY m.id, m.nombre
    HAVING stock < 10
    ORDER BY stock ASC
");
$nombre_archivo = 'Reporte_Farmacia_' . date('Ymd_His') . '.xls';
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$nombre_archivo\"");
header("Pragma: no-cache");
header("Expires: 0");
echo "\xEF\xBB\xBF";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; color: #1e293b; }
        .membrete { text-align: center; border-bottom: 3px solid #065f46; padding-bottom: 12px; margin-bottom: 20px; }
        .membrete h1 { font-size: 16pt; color: #065f46; margin: 2px 0; letter-spacing: 1px; }
        .membrete h2 { font-size: 13pt; margin: 2px 0; font-weight: normal; color: #334155; }
        .membrete .titulo-reporte { font-size: 14pt; color: #065f46; font-weight: bold; margin-top: 10px; }
        
        .info-box { background: #f0fdf4; padding: 12px 15px; margin-bottom: 20px; border-left: 4px solid #065f46; }
        .info-box table { width: 100%; font-size: 10pt; }
        .info-box td { padding: 3px 0; }
        
        .kpi-table { width: 100%; margin-bottom: 20px; }
        .kpi-table td { width: 50%; padding: 15px; text-align: center; background: #ecfdf5; border: 1px solid #065f46; }
        .kpi-table .kpi-numero { font-size: 22pt; font-weight: bold; color: #065f46; display: block; }
        .kpi-table .kpi-label { font-size: 10pt; color: #334155; }
        
        h3 { color: #065f46; font-size: 12pt; margin: 25px 0 10px; padding: 8px 12px; background: #ecfdf5; border-left: 4px solid #065f46; }
        
        table.datos { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 10pt; }
        table.datos th { background: #065f46; color: white; padding: 10px; text-align: left; border: 1px solid #065f46; font-weight: bold; }
        table.datos td { padding: 8px 10px; border: 1px solid #cbd5e1; }
        table.datos tr:nth-child(even) td { background: #f8fafc; }
        
        .critico { color: #dc2626; font-weight: bold; }
        .bajo { color: #d97706; font-weight: bold; }
        .ok { color: #16a34a; font-weight: bold; }
        
        .firmas { margin-top: 40px; }
        .firmas table { width: 100%; }
        .firmas td { text-align: center; padding: 30px 10px 5px; font-size: 10pt; }
        .firma-linea { border-top: 1px solid #000; margin: 0 20px 5px; padding-top: 30px; }
        
        .footer { text-align: center; font-size: 8pt; color: #64748b; margin-top: 30px; padding-top: 10px; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
<div class="membrete">
    <h1>REPÚBLICA DE NICARAGUA</h1>
    <h2>MINISTERIO DE SALUD - MINSA</h2>
    <h2>Sistema Local de Atención Integral en Salud - SILAIS</h2>
    <div class="titulo-reporte">REPORTE DE GESTIÓN FARMACÉUTICA</div>
</div>
<div class="info-box">
    <table>
        <tr>
            <td><strong>Período evaluado:</strong> <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> al <?php echo date('d/m/Y', strtotime($fecha_fin)); ?></td>
        </tr>
        <tr>
            <td><strong>Fecha de emisión:</strong> <?php echo date('d/m/Y H:i'); ?></td>
        </tr>
        <tr>
            <td><strong>Elaborado por:</strong> <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?> - Jefe de Farmacia</td>
        </tr>
    </table>
</div>
<table class="kpi-table">
    <tr>
        <td>
            <span class="kpi-numero"><?php echo number_format($total_recetas); ?></span>
            <span class="kpi-label">Total de Recetas</span>
        </td>
        <td>
            <span class="kpi-numero"><?php echo number_format($total_unidades); ?></span>
            <span class="kpi-label">Unidades Dispensadas</span>
        </td>
    </tr>
</table>
<h3>1. DISTRIBUCIÓN DE RECETAS POR ESTADO</h3>
<table class="datos">
    <thead>
        <tr>
            <th>Estado</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($e = $estados->fetch_assoc()): ?>
        <tr>
            <td><?php echo ucfirst($e['estado']); ?></td>
            <td><?php echo $e['total']; ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<h3>2. TOP 20 MEDICAMENTOS MÁS RECETADOS</h3>
<table class="datos">
    <thead>
        <tr>
            <th style="width: 50px;">No.</th>
            <th>Medicamento</th>
            <th style="width: 150px;">Unidades</th>
        </tr>
    </thead>
    <tbody>
        <?php $i = 1; while ($m = $top_meds->fetch_assoc()): ?>
        <tr>
            <td><?php echo $i++; ?></td>
            <td><?php echo htmlspecialchars($m['nombre']); ?></td>
            <td><?php echo $m['total']; ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<h3>3. TOP 10 DOCTORES POR EMISIÓN DE RECETAS</h3>
<table class="datos">
    <thead>
        <tr>
            <th style="width: 50px;">No.</th>
            <th>Doctor</th>
            <th style="width: 150px;">Recetas</th>
        </tr>
    </thead>
    <tbody>
        <?php $i = 1; while ($d = $top_doctores->fetch_assoc()): ?>
        <tr>
            <td><?php echo $i++; ?></td>
            <td><?php echo htmlspecialchars($d['nombre']); ?></td>
            <td><?php echo $d['total']; ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<h3>4. MEDICAMENTOS CON STOCK CRÍTICO</h3>
<table class="datos">
    <thead>
        <tr>
            <th style="width: 50px;">No.</th>
            <th>Medicamento</th>
            <th style="width: 100px;">Stock</th>
            <th style="width: 120px;">Estado</th>
        </tr>
    </thead>
    <tbody>
        <?php $i = 1; while ($s = $stock_critico->fetch_assoc()): 
            $stock = (int)$s['stock'];
        ?>
        <tr>
            <td><?php echo $i++; ?></td>
            <td><?php echo htmlspecialchars($s['nombre']); ?></td>
            <td><?php echo $stock; ?></td>
            <td class="<?php echo $stock < 5 ? 'critico' : 'bajo'; ?>">
                <?php echo $stock < 5 ? 'CRÍTICO' : 'BAJO'; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<div class="firmas">
    <table>
        <tr>
            <td>
                <div class="firma-linea"></div>
                <strong>Elaborado por</strong><br>
                <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?><br>
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
                <strong>Autorizado por</strong><br>
                SILAIS / Región<br>
                &nbsp;
            </td>
        </tr>
    </table>
</div>

<div class="footer">
    Sistema de Trazabilidad de Farmacia - Reporte generado automáticamente
</div>

</body>
</html>