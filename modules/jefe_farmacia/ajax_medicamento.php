<?php
if (session_status() === PHP_SESSION_NONE) {
    session_save_path('/tmp'); // Clave para Vercel
    session_start();
}
require_once '../../config/database.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {

    exit;
}

$id = intval($_GET['id']);
$sql = "SELECT * FROM medicamentos WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    header('Content-Type: application/json');
    echo json_encode($row);
} else {
    echo json_encode([]);
}
?>