<?php
require_once 'config/database.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$sql = "SELECT p.nombre AS paciente, m.nombre AS medicamento, rd.dosis, rd.cantidad 
        FROM recetas r
        INNER JOIN pacientes p ON r.paciente_id = p.id
        INNER JOIN receta_detalles rd ON r.id = rd.receta_id
        INNER JOIN medicamentos m ON rd.medicamento_id = m.id
        WHERE r.id = $id";

$res = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Receta Médica Visual</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f0f4f8; font-family: Arial, sans-serif; }
        .card-adulto { border-radius: 25px; border: none; box-shadow: 0 8px 20px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .icono-grande { font-size: 5rem; display: block; margin: 15px 0; }
        .texto-grande { font-size: 1.8rem; font-weight: bold; }
    </style>
</head>
<body>
<div class="container py-4 text-center">
    
    <div class="p-3 bg-white rounded-4 mb-4 shadow-sm">
        <h2 class="text-primary fw-bold">👴👵 Tu Receta Médica</h2>
        <?php if($row_p = $res->fetch_assoc()): $res->data_seek(0); ?>
            <p class="fs-4 m-0">Paciente: <strong><?php echo htmlspecialchars($row_p['paciente']); ?></strong></p>
        <?php endif; ?>
    </div>

    <?php 
    if($res && $res->num_rows > 0):
        while($med = $res->fetch_assoc()):
            $dosis_texto = strtolower($med['dosis']);
            $icono = "fa-pills text-primary"; 
            $momento = "Tomar según indicación médica";
            
            if (strpos($dosis_texto, 'mañana') !== false || strpos($dosis_texto, 'desayuno') !== false || strpos($dosis_texto, '8h') !== false) {
                $icono = "fa-cloud-sun text-warning";
                $momento = "POR LA MAÑANA (Al Despertar)";
            } elseif (strpos($dosis_texto, 'noche') !== false || strpos($dosis_texto, 'dormir') !== false) {
                $icono = "fa-moon text-indigo";
                $momento = "POR LA NOCHE (Antes de Dormir)";
            } elseif (strpos($dosis_texto, 'tarde') !== false || strpos($dosis_texto, 'almuerzo') !== false) {
                $icono = "fa-sun text-danger";
                $momento = "A MEDIODÍA (Con el Almuerzo)";
            }
    ?>
        <div class="card card-adulto bg-white p-4">
            <div class="card-body">
                <h3 class="text-dark fw-bold text-uppercase fs-2">💊 <?php echo htmlspecialchars($med['medicamento']); ?></h3>
                <hr>
                
                <i class="fas <?php echo $icono; ?> icono-grande"></i>
                
                <p class="texto-grande text-success mb-2"><?php echo $momento; ?></p>
                <p class="fs-3 text-muted m-0">Cantidad: <strong><?php echo $med['cantidad']; ?> Pastillas</strong></p>
                <p class="fs-4 text-secondary italic">Indicación: "<?php echo htmlspecialchars($med['dosis']); ?>"</p>
            </div>
        </div>
    <?php 
        endwhile;
    else:
        echo "<div class='alert alert-danger'>No se encontró la receta.</div>";
    endif; 
    ?>
</div>
</body>
</html>