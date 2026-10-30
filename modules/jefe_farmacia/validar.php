<?php
if (session_status() === PHP_SESSION_NONE) {
    session_save_path('/tmp'); // Clave para Vercel
    session_start();
}
require_once '../../config/database.php';
header('ngrok-skip-browser-warning: 69420');

$token = trim($_GET['token']); 

if (empty($token)) {
    die("<div style='text-align:center; padding:50px;'><h1>Error: Receta no válida.</h1><p>No se proporcionó un token de acceso.</p></div>");
}


$sql = "SELECT r.*, p.nombre as paciente, p.apellido as apellido, u.nombre as doctor
        FROM recetas r
        JOIN pacientes p ON r.paciente_id = p.id
        JOIN usuarios u ON r.doctor_id = u.id
        WHERE r.token_acceso = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $token);
$stmt->execute();
$resultado = $stmt->get_result();
$receta = $resultado->fetch_assoc();

if (!$receta) {
    die("<div style='text-align:center; padding:50px;'><h1>Error: Receta no encontrada.</h1><p>El enlace podría estar dañado o la receta no existe.</p></div>");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Validación de Receta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card shadow border-0 p-4">
            <h2 class="text-primary mb-4 text-center">Receta Médica Digital #<?php echo $receta['id']; ?></h2>
            <p><strong>Paciente:</strong> <?php echo htmlspecialchars($receta['paciente'] . ' ' . $receta['apellido']); ?></p>
            <p><strong>Doctor:</strong> <?php echo htmlspecialchars($receta['doctor']); ?></p>
            <p><strong>Estado:</strong> <span class="badge bg-<?php echo ($receta['estado'] == 'aprobada' ? 'success' : 'warning'); ?>"><?php echo ucfirst($receta['estado']); ?></span></p>

            <h4 class="mt-4">Medicamentos</h4>
            <ul class="list-group mb-4">
                <?php
                $sql_med = "SELECT m.nombre, rd.cantidad, rd.dosis
                            FROM receta_detalles rd
                            JOIN medicamentos m ON rd.medicamento_id = m.id
                            WHERE rd.receta_id = ?";
                $stmt_med = $conn->prepare($sql_med);
                $stmt_med->bind_param("i", $receta['id']);
                $stmt_med->execute();
                $res_med = $stmt_med->get_result();
                
                while($med = $res_med->fetch_assoc()){
                    echo "<li class='list-group-item'><strong>{$med['nombre']}</strong> - Cantidad: {$med['cantidad']} | Dosis: {$med['dosis']}</li>";
                }
                ?>
            </ul>

            <?php if($receta['estado'] == 'aprobada'): ?>
                <div class="alert alert-success text-center">Esta receta está aprobada para su entrega.</div>
            <?php else: ?>
                <div class="alert alert-warning text-center">Esta receta aún se encuentra en estado: <?php echo $receta['estado']; ?></div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>