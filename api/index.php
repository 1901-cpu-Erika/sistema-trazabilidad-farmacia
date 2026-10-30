<?php
// api/index.php - Enrutador principal para Vercel Hobby

// Obtiene la ruta solicitada (ej: /modules/admin/dashboard.php)
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Elimina la barra inicial
$path = ltrim($path, '/');

// Si la ruta está vacía, carga el login
if ($path === '') {
    $path = 'login.php';
}

// Construye la ruta real del archivo en el proyecto
$file = __DIR__ . '/../' . $path;

// Verifica que el archivo exista y sea un PHP
if (file_exists($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
    // Cambia el directorio de trabajo para que las rutas relativas funcionen
    chdir(dirname($file));
    require $file;
} else {
    // Si no existe, muestra un error 404
    http_response_code(404);
    echo "404 - Archivo no encontrado: " . htmlspecialchars($path);
}
?>