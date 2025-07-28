<?php
// Incluimos la configuración y el header.
// header.php ya se encarga de iniciar la sesión.
require_once __DIR__ . '/database.php';
include __DIR__ . '/header.php';
?>

<div class="px-4 py-5 my-5 text-center">
    <h1 class="display-5 fw-bold">Bienvenido a Keeper</h1>
    <div class="col-lg-6 mx-auto">
        <p class="lead mb-4">
            <?php if (isset($_SESSION['user_id'])): ?>
                ¡Hola, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>! Has iniciado sesión correctamente.
                <br>
                Desde aquí podrás gestionar tus finanzas y contraseñas de forma segura.
            <?php else: ?>
                Esta es tu aplicación todo en uno para gestionar finanzas y contraseñas de forma segura.
                <br>
                Por favor, <a href="login.php">inicia sesión</a> o <a href="register.php">regístrate</a> para comenzar.
            <?php endif; ?>
        </p>
    </div>
</div>

<?php
// Incluimos el footer.
include __DIR__ . '/footer.php';
?>