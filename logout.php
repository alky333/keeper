<?php
// Iniciar la sesión para poder acceder a las variables de sesión.
session_start();

// Eliminar todas las variables de sesión.
$_SESSION = [];

// Si se desea destruir la sesión completamente, borra también la cookie de sesión.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir la sesión.
session_destroy();

// Redirigir al usuario a la página principal.
header('Location: index.php');
exit;