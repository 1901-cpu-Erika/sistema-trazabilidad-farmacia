<?php
if (session_status() === PHP_SESSION_NONE) {
    session_save_path('/tmp');
    session_start();
}
date_default_timezone_set('America/Managua');
require_once '../../config/database.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] != 'jefe_farmacia') {
    header("Location: /login.php");
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
        ORDER BY total DESC LIMIT 15";
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
    LIMIT 15
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Farmacia</title>
    <style>
        /* ESTILOS DE PANTALLA */
        body { font-family: Arial, sans-serif; background: #f1f5f9; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 40px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        
        /* ESTILOS DEL PDF */
        @media print {
            body { background: white; padding: 0; }
            .container { box-shadow: none; padding: 0; max-width: 100%; }
            .no-print { display: none !important; }
            @page { margin: 20px; size: A4 portrait; }
        }

        .membrete { text-align: center; border-bottom: 3px solid #065f46; padding-bottom: 10px; margin-bottom: 15px; }
        .membrete h1 { font-size: 14px; color: #065f46; margin: 2px 0; }
        .membrete h2 { font-size: 12px; margin: 2px 0; font-weight: normal; color: #334155; }
        .membrete h3 { font-size: 11px; margin: 2px 0; color: #475569; font-weight: normal; }
        .membrete .titulo-reporte { font-size: 13px; color: #065f46; font-weight: bold; margin-top: 10px; padding-top: 8px; border-top: 1px dashed #cbd5e1; }

        .info-box { background: #f0fdf4; padding: 10px 12px; margin-bottom: 15px; border-left: 4px solid #065f46; font-size: 10px; }
        .info-box td { padding: 2px 0; }

        .kpi-table { width: 100%; margin-bottom: 15px; border-collapse: separate; border-spacing: 10px 0; }
        .kpi-table td { width: 50%; padding: 12px; text-align: center; background: #ecfdf5; border: 1px solid #065f46; }
        .kpi-numero { font-size: 22px; font-weight: bold; color: #065f46; display: block; }
        .kpi-label { font-size: 10px; color: #334155; }

        h3 { color: #065f46; font-size: 11px; margin: 15px 0 8px; padding: 6px 10px; background: #ecfdf5; border-left: 4px solid #065f46; }

        table.datos { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 10px; }
        table.datos th { background: #065f46; color: white; padding: 7px; text-align: left; border: 1px solid #065f46; }
        table.datos td { padding: 5px 7px; border: 1px solid #cbd5e1; }
        table.datos tr:nth-child(even) td { background: #f8fafc; }

        .critico { color: #dc2626; font-weight: bold; }
        .bajo { color: #d97706; font-weight: bold; }

        .firmas { margin-top: 40px; page-break-inside: avoid; }
        .firmas table { width: 100%; }
        .firmas td { text-align: center; padding: 35px 10px 5px; font-size: 10px; }
        .firma-linea { border-top: 1px solid #000; margin: 0 15px 5px; padding-top: 30px; }

        .footer { text-align: center; font-size: 8px; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 5px; margin-top: 20px; }
        
        /* BOTÓN FLOTANTE */
        .btn-print {
            position: fixed; bottom: 30px; right: 30px; background: #065f46; color: white;
            padding: 15px 25px; border-radius: 50px; border: none; font-size: 16px; font-weight: bold;
            cursor: pointer; box-shadow: 0 4px 15px rgba(6, 95, 70, 0.4); transition: all 0.3s;
        }
        .btn-print:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(6, 95, 70, 0.6); }
    </style>
</head>
<body>

<!-- BOTÓN PARA GUARDAR COMO PDF -->
<button class="btn-print no-print" onclick="window.print()">
    <i class="fas fa-file-pdf"></i> Guardar como PDF
</button>

<div class="container">
    <div class="membrete">
        <h1>REPÚBLICA DE NICARAGUA</h1>
        <h2>MINISTERIO DE SALUD - MINSA</h2>
        <h3>Sistema Local de Atención Integral en Salud - SILAIS</h3>
        <div class="titulo-reporte">REPORTE DE GESTIÓN FARMACÉUTICA</div>
    </div>

    <div class="info-box">
        <table>
            <tr><td><strong>Período:</strong> <?php echo date('d/m/Y', strtotime($fecha_inicio)) . ' al ' . date('d/m/Y', strtotime($fecha_fin)); ?></td></tr>
            <tr><td><strong>Emisión:</strong> <?php echo date('d/m/Y H:i'); ?></td></tr>
            <tr><td><strong>Elaborado por:</strong> <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?> - Jefe de Farmacia</td></tr>
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
        <tr><th>Estado</th><th>Total</th></tr>
        <?php while ($e = $estados->fetch_assoc()): ?>
            <tr><td><?php echo ucfirst($e['estado']); ?></td><td><?php echo $e['total']; ?></td></tr>
        <?php endwhile; ?>
    </table>

    <h3>2. TOP 15 MEDICAMENTOS MÁS RECETADOS</h3>
    <table class="datos">
        <tr><th style="width: 30px;">No.</th><th>Medicamento</th><th style="width: 80px;">Unidades</th></tr>
        <?php $i = 1; while ($m = $top_meds->fetch_assoc()): ?>
            <tr><td><?php echo $i++; ?></td><td><?php echo htmlspecialchars($m['nombre']); ?></td><td><?php echo $m['total']; ?></td></tr>
        <?php endwhile; ?>
    </table>

    <h3>3. TOP 10 DOCTORES POR EMISIÓN DE RECETAS</h3>
    <table class="datos">
        <tr><th style="width: 30px;">No.</th><th>Doctor</th><th style="width: 80px;">Recetas</th></tr>
        <?php $i = 1; while ($d = $top_doctores->fetch_assoc()): ?>
            <tr><td><?php echo $i++; ?></td><td><?php echo htmlspecialchars($d['nombre']); ?></td><td><?php echo $d['total']; ?></td></tr>
        <?php endwhile; ?>
    </table>

    <h3>4. MEDICAMENTOS CON STOCK CRÍTICO</h3>
    <table class="datos">
        <tr><th style="width: 30px;">No.</th><th>Medicamento</th><th style="width: 60px;">Stock</th><th style="width: 80px;">Estado</th></tr>
        <?php $i = 1; while ($s = $stock_critico->fetch_assoc()): 
            $stock = (int)$s['stock'];
            $clase = $stock < 5 ? 'critico' : 'bajo';
            $estado = $stock < 5 ? 'CRÍTICO' : 'BAJO';
        ?>
            <tr><td><?php echo $i++; ?></td><td><?php echo htmlspecialchars($s['nombre']); ?></td><td><?php echo $stock; ?></td><td class="<?php echo $clase; ?>"><?php echo $estado; ?></td></tr>
        <?php endwhile; ?>
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
        Sistema de Trazabilidad de Farmacia | Generado automáticamente | Página 1
    </div>
</div>

</body>
</html>