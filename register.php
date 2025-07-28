<?php
// 1. Incluimos la conexión a la base de datos y la configuración
require_once __DIR__ . '/database.php';

$error = '';
$success = '';

// 2. Verificamos si el formulario ha sido enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Por favor, completa todos los campos.';
    } else {
        // 3. Comprobamos si el usuario ya existe para evitar duplicados
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'El nombre de usuario ya está en uso.';
        } else {
            // 4. Hasheamos la contraseña de forma segura
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // 5. Insertamos el nuevo usuario en la base de datos usando sentencias preparadas
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
            if ($stmt->execute([$username, $password_hash])) {
                $success = '¡Usuario registrado con éxito! Ya puedes iniciar sesión.';
            } else {
                $error = 'Hubo un error al registrar el usuario.';
            }
        }
    }
}

// 6. Incluimos la cabecera de la página
include __DIR__ . '/header.php';
?>

<h1>Registrarse</h1>

<form action="register.php" method="POST">
    <div class="mb-3">
        <label for="username" class="form-label">Nombre de Usuario</label>
        <input type="text" class="form-control" id="username" name="username" required>
    </div>
    <div class="mb-3">
        <label for="password" class="form-label">Contraseña</label>
        <input type="password" class="form-control" id="password" name="password" required>
    </div>
    <button type="submit" class="btn btn-primary">Registrarse</button>
</form>

<?php
// 7. Incluimos el pie de página
include __DIR__ . '/footer.php';
?>