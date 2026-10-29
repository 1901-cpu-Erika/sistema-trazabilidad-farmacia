<?php
$host      = getenv('MYSQLHOST')     ?: 'localhost';
$user      = getenv('MYSQLUSER')     ?: 'root';
$password  = getenv('MYSQLPASSWORD') ?: '';
$database  = getenv('MYSQLDATABASE') ?: 'farmacia_trazabilidad'; 
$puerto    = getenv('MYSQLPORT')     ?: 3306;

$conn = new mysqli($host, $user, $password, $database, (int)$puerto);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>