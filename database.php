<?php

// Define la URL base de tu proyecto. Ajústala si tu carpeta no se llama "keeper".
define('BASE_URL', '/keeper');

define('DB_HOST', 'localhost');
define('DB_NAME', 'my_app');
define('DB_USER', 'root'); // Cambia esto por tu usuario de DB
define('DB_PASS', '');     // Cambia esto por tu contraseña de DB

// Clave de encriptación. ¡MUY IMPORTANTE!
// En un entorno real, NUNCA la guardes aquí. Úsala desde una variable de entorno.
// Para generar una clave segura, puedes usar: bin2hex(random_bytes(32))
define('ENCRYPTION_KEY', 'tu_super_secreta_clave_de_32_bytes_aqui');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    // Configurar PDO para que lance excepciones en caso de error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Desactivar emulación de sentencias preparadas para mayor seguridad
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    // En un entorno de producción, no muestres el error detallado al usuario.
    die("Error de conexión a la base de datos: " . $e->getMessage());
}