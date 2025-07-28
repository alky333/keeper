<?php
// 1. Incluir la configuración y la conexión a la BD.
require_once __DIR__ . '/database.php';

// Iniciamos la sesión aquí para poder comprobar si el usuario ya está logueado
// antes de enviar cualquier contenido HTML. El header tiene un control para no iniciarla dos veces.
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

// 2. Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Por favor, introduce tu nombre de usuario y contraseña.';
    } else {
        // 3. Buscar al usuario en la base de datos
        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // 4. Verificar si el usuario existe y si la contraseña es correcta
        if ($user && password_verify($password, $user['password_hash'])) {
            // 5. Iniciar sesión: guardar el ID y nombre del usuario en la sesión
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            // 6. Redirigir al usuario a la página principal
            header('Location: index.php');
            exit;
        } else {
            $error = 'Nombre de usuario o contraseña incorrectos.';
        }
    }
}

// 7. Incluir la cabecera
include __DIR__ . '/header.php';
?>

<h1>Iniciar Sesión</h1>

<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<form action="login.php" method="POST">
    <div class="mb-3">
        <label for="username" class="form-label">Nombre de Usuario</label>
        <input type="text" class="form-control" id="username" name="username" required>
    </div>
    <div class="mb-3">
        <label for="password" class="form-label">Contraseña</label>
        <input type="password" class="form-control" id="password" name="password" required>
    </div>
    <button type="submit" class="btn btn-primary">Iniciar Sesión</button>
</form>

<?php
// 8. Incluir el pie de página
include __DIR__ . '/footer.php';
?>