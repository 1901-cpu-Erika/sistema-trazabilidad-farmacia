<?php
// Vercel tiene una variable de entorno llamada 'VERCEL' que vale '1' en producción
$en_vercel = getenv('VERCEL') === '1';

if (!$en_vercel) {
    // ==========================================
    // CONEXIÓN LOCAL (XAMPP)
    // ==========================================
    $host     = "localhost";
    $user     = "root";
    $password = "";
    $database = "farmacia_trazabilidad";
    $port     = 3306;

    $conn = new mysqli($host, $user, $password, $database, $port);
    
    if ($conn->connect_error) {
        die("Error de conexión local: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");

} else {
    // ==========================================
    // CONEXIÓN PRODUCCIÓN (AIVEN/Vercel)
    // ==========================================
    $host     = "mysql-trazabilidad-sistematrazabilidad.e.aivencloud.com";
    $port     = 16692;
    $user     = "avnadmin";
    $password = "AVNS_fH1PyC3qj6S7l-n-9De"; // Tu contraseña de Aiven
    $database = "defaultdb";
    
    // Ruta al certificado SSL
    $ssl_ca = __DIR__ . '/../ca.pem';

    // Inicializamos mysqli y configuramos el SSL
    $conn = mysqli_init();
    mysqli_ssl_set($conn, NULL, NULL, $ssl_ca, NULL, NULL);
    
    // Conectamos
    if (!mysqli_real_connect($conn, $host, $user, $password, $database, $port)) {
        die("Error de conexión a Aiven: " . mysqli_connect_error());
    }
    $conn->set_charset("utf8mb4");
}
?>