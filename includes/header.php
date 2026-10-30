<?php
if (session_status() === PHP_SESSION_NONE) {
    session_save_path('/tmp');
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header("Location: http://" . $_SERVER['HTTP_HOST'] . "/sistema-trazabilidad-farmacia/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Trazabilidad de Fármacos</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="/sistema-trazabilidad-farmacia/assets/css/style.css">
    <link rel="stylesheet" href="/sistema-trazabilidad-farmacia/assets/css/glassmorphism.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm mb-4">
        <div class="container-fluid">
            
            <!-- ÚNICO CAMBIO: Botón para abrir el menú en celular -->
            <button class="btn btn-dark d-md-none me-3" type="button" onclick="document.querySelector('.sidebar').classList.toggle('show')">
                <i class="fas fa-bars"></i>
            </button>

            <a class="navbar-brand fw-bold" href="#">Trazabilidad</a>
            
            <div class="ms-auto d-flex align-items-center">
                <!-- (Aquí quité la etiqueta </a> que estaba suelta) -->
            </div>
        </div>
    </nav>
    <div class="container-fluid">
        <div class="row">