<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
} ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keeper</title>
    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Tu CSS personalizado -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/style.css">
</head>
<body>
<canvas id="matrix-bg"></canvas>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="<?php echo BASE_URL; ?>/">
            <svg width="24" height="24" viewBox="0 0 24 24" class="me-2" xmlns="http://www.w3.org/2000/svg" style="image-rendering: pixelated;">
                <rect x="4" y="8" width="2" height="2" fill="#00ff50"/>
                <rect x="6" y="6" width="2" height="2" fill="#00ff50"/>
                <rect x="8" y="4" width="8" height="2" fill="#00ff50"/>
                <rect x="16" y="6" width="2" height="2" fill="#00ff50"/>
                <rect x="18" y="8" width="2" height="2" fill="#00ff50"/>
                <rect x="6" y="8" width="2" height="2" fill="#fff"/>
                <rect x="16" y="8" width="2" height="2" fill="#fff"/>
                <rect x="8" y="10" width="2" height="2" fill="#00ff50"/>
                <rect x="14" y="10" width="2" height="2" fill="#00ff50"/>
                <rect x="6" y="12" width="12" height="2" fill="#00ff50"/>
                <rect x="6" y="14" width="2" height="2" fill="#00ff50"/>
                <rect x="10" y="14" width="4" height="2" fill="#fff"/>
                <rect x="16" y="14" width="2" height="2" fill="#00ff50"/>
                <rect x="4" y="16" width="2" height="2" fill="#00ff50"/>
                <rect x="18" y="16" width="2" height="2" fill="#00ff50"/>
            </svg>
            Keeper
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>/dashboard.php">Deudas</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>/passwords.php">Contraseñas</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>/logout.php">Cerrar Sesión</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>/login.php">Iniciar Sesión</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>/register.php">Registrarse</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Contenedor para las notificaciones flotantes -->
<div class="notification-container">
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
</div>

<main class="container mt-4">
