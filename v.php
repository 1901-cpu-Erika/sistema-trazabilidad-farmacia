<?php
$token = $_GET['t'] ?? '';
if (!empty($token)) {
    header("Location: modules/admin/validar.php?token=" . urlencode($token));
    exit;
} else {
    die("Error: Token no válido.");
}
?>