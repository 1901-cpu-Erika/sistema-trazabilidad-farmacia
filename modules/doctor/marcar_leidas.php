<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../config/database.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'doctor') {
    echo json_encode(['status' => 'error']);
    exit();
}

$doctor_id = $_SESSION['usuario_id'];

$sql = "UPDATE notificaciones n 
        JOIN recetas r ON n.paciente_id = r.paciente_id 
        SET n.leida = 1 
        WHERE r.doctor_id = ? AND n.leida = 0";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $doctor_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error']);
}
?>