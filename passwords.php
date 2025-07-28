<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/encryption.php'; // Incluimos nuestro nuevo módulo de encriptación

// Proteger la página
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Lógica para añadir una nueva contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // --- ACCIÓN: AÑADIR CONTRASEÑA ---
    if ($action === 'add_password') {
    $platform = $_POST['platform'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $security_code = $_POST['security_code'] ?? '';
    $notes = $_POST['notes'] ?? '';

    if (empty($platform) || empty($username) || empty($password)) {
        $error = "Por favor, completa los campos de plataforma, usuario y contraseña.";
    } else {
        // Encriptamos todos los datos sensibles antes de guardarlos
        $encrypted_password = encrypt_data($password, ENCRYPTION_KEY);
        $encrypted_security_code = !empty($security_code) ? encrypt_data($security_code, ENCRYPTION_KEY) : null;
        $encrypted_notes = !empty($notes) ? encrypt_data($notes, ENCRYPTION_KEY) : null;

        $sql = "INSERT INTO passwords (user_id, platform, username, encrypted_password, encrypted_security_code, encrypted_notes) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);

        if ($stmt->execute([$user_id, $platform, $username, $encrypted_password, $encrypted_security_code, $encrypted_notes])) {
            $success = "Contraseña para '{$platform}' guardada correctamente.";
        } else {
            $error = "Error al guardar la contraseña.";
        }
    }
    }

    // --- ACCIÓN: ACTUALIZAR CONTRASEÑA (MODO AJAX) ---
    if ($action === 'update_password') {
        $password_id = $_POST['password_id'] ?? 0;
        $platform = $_POST['platform'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? ''; // Puede estar vacío si no se quiere cambiar
        $security_code = $_POST['security_code'] ?? '';
        $notes = $_POST['notes'] ?? '';
        
        $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $response = ['status' => 'error', 'message' => 'Error al procesar la solicitud.'];

        if ($password_id && !empty($platform) && !empty($username)) {
            $stmt = $pdo->prepare("SELECT encrypted_password FROM passwords WHERE id = ? AND user_id = ?");
            $stmt->execute([$password_id, $user_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $encrypted_password = !empty($password) ? encrypt_data($password, ENCRYPTION_KEY) : $existing['encrypted_password'];
                $encrypted_security_code = !empty($security_code) ? encrypt_data($security_code, ENCRYPTION_KEY) : null;
                $encrypted_notes = !empty($notes) ? encrypt_data($notes, ENCRYPTION_KEY) : null;

                $sql = "UPDATE passwords SET platform = ?, username = ?, encrypted_password = ?, encrypted_security_code = ?, encrypted_notes = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                
                if ($stmt->execute([$platform, $username, $encrypted_password, $encrypted_security_code, $encrypted_notes, $password_id])) {
                    $response['status'] = 'success';
                    $response['message'] = 'Contraseña actualizada correctamente.';
                    // Devolvemos los datos actualizados y desencriptados para la UI
                    $response['updatedData'] = [
                        'id' => $password_id,
                        'platform' => $platform,
                        'username' => $username,
                        'password' => !empty($password) ? $password : decrypt_data($existing['encrypted_password'], ENCRYPTION_KEY),
                        'code' => $security_code,
                        'notes' => $notes
                    ];
                } else {
                    $response['message'] = 'Error al actualizar la contraseña en la base de datos.';
                }
            } else {
                $response['message'] = 'Operación no permitida o la contraseña no existe.';
            }
        } else {
            $response['message'] = 'Faltan datos para actualizar la contraseña.';
        }

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        } else {
            if ($response['status'] === 'success') $success = $response['message'];
            else $error = $response['message'];
        }
    }

    // --- ACCIÓN: ELIMINAR CONTRASEÑA ---
    if ($action === 'delete_password') {
        $password_id = $_POST['password_id'] ?? 0;
        $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $response = ['status' => 'error', 'message' => 'Error al procesar la solicitud.'];

        if ($password_id) {
            // Verificamos que la contraseña pertenece al usuario antes de borrar
            $stmt = $pdo->prepare("DELETE FROM passwords WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$password_id, $user_id]) && $stmt->rowCount() > 0) {
                $response = ['status' => 'success', 'message' => 'Contraseña eliminada correctamente.'];
            } else {
                $response['message'] = 'Error al eliminar la contraseña o no se encontró.';
            }
        } else {
            $response['message'] = 'ID de contraseña no válido.';
        }

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        } else {
            // Fallback para navegadores sin JavaScript
            if ($response['status'] === 'success') $success = $response['message'];
            else $error = $response['message'];
        }
    }
}

// Obtener todas las contraseñas del usuario
$stmt = $pdo->prepare("SELECT id, platform, username, encrypted_password, encrypted_security_code, encrypted_notes FROM passwords WHERE user_id = ? ORDER BY platform ASC");
$stmt->execute([$user_id]);
$passwords = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/header.php';
?>

<h1>Gestor de Contraseñas</h1>

<!-- Formulario para añadir una nueva contraseña -->
<div class="p-4 mb-4" style="background-color: rgba(0,0,0,0.2); border: 1px solid #00ff50;">
    <h2>Añadir Nueva Contraseña</h2>
    <form action="passwords.php" method="POST">
        <input type="hidden" name="action" value="add_password">
        <div class="row">
            <div class="col-md-3 mb-3"><label for="platform" class="form-label">Plataforma</label><input type="text" class="form-control" id="platform" name="platform" required></div>
            <div class="col-md-3 mb-3"><label for="username" class="form-label">Usuario / Email</label><input type="text" class="form-control" id="username" name="username" required></div>
            <div class="col-md-3 mb-3"><label for="password" class="form-label">Contraseña</label><input type="password" class="form-control" id="password" name="password" required></div>
            <div class="col-md-3 mb-3"><label for="security_code" class="form-label">Código Seguridad (2FA)</label><input type="text" class="form-control" id="security_code" name="security_code"></div>
            <div class="col-md-12 mb-3"><label for="notes" class="form-label">Notas</label><textarea class="form-control" id="notes" name="notes" rows="2"></textarea></div>
        </div>
        <button type="submit" class="btn btn-primary">Guardar Contraseña</button>
    </form>
</div>

<!-- Listado de contraseñas guardadas -->
<h2>Mis Contraseñas</h2>
<?php if (empty($passwords)): ?>
    <p>No tienes ninguna contraseña guardada. ¡Añade la primera!</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle" style="color: #e0e0e0;">
            <thead>
                <tr>
                    <th>Plataforma</th>
                    <th>Usuario</th>
                    <th>Contraseña</th>
                    <th style="width: 15%;">Cód. Seguridad</th>
                    <th>Notas</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($passwords as $p): ?>
                <?php
                    $decrypted_pass = decrypt_data($p['encrypted_password'], ENCRYPTION_KEY);
                    $decrypted_code = !empty($p['encrypted_security_code']) ? decrypt_data($p['encrypted_security_code'], ENCRYPTION_KEY) : '';
                    $decrypted_notes = !empty($p['encrypted_notes']) ? decrypt_data($p['encrypted_notes'], ENCRYPTION_KEY) : '';
                ?>
                <tr id="password-row-<?php echo $p['id']; ?>">
                    <td><?php echo htmlspecialchars($p['platform']); ?></td>
                    <td><input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars($p['username']); ?>" readonly></td>
                    <td><input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars($decrypted_pass); ?>" readonly></td>
                    <td><input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars($decrypted_code); ?>" readonly></td>
                    <td><textarea class="form-control form-control-sm" readonly><?php echo htmlspecialchars($decrypted_notes); ?></textarea></td>
                    <td>
                        <div class="btn-group-vertical w-100 mb-2" role="group" aria-label="Acciones de copiado">
                            <button class="btn btn-sm btn-primary" onclick="copyToClipboard(this, '<?php echo htmlspecialchars($p['username'], ENT_QUOTES); ?>')">Copiar Usuario</button>
                            <button class="btn btn-sm btn-primary" onclick="copyToClipboard(this, '<?php echo htmlspecialchars($decrypted_pass, ENT_QUOTES); ?>')">Copiar Pass</button>
                            <?php if ($decrypted_code): ?>
                            <button class="btn btn-sm btn-secondary" onclick="copyToClipboard(this, '<?php echo htmlspecialchars($decrypted_code, ENT_QUOTES); ?>')">Copiar Código</button>
                            <?php endif; ?>
                        </div>
                        <div class="btn-group-vertical w-100" role="group" aria-label="Acciones de gestión">
                            <button type="button" class="btn btn-sm btn-info btn-edit-password"
                                data-id="<?php echo $p['id']; ?>"
                                data-platform="<?php echo htmlspecialchars($p['platform']); ?>"
                                data-username="<?php echo htmlspecialchars($p['username']); ?>"
                                data-password="<?php echo htmlspecialchars($decrypted_pass); ?>"
                                data-code="<?php echo htmlspecialchars($decrypted_code); ?>"
                                data-notes="<?php echo htmlspecialchars($decrypted_notes); ?>">Editar</button>
                            <button type="button" class="btn btn-sm btn-danger btn-delete-password"
                                data-id="<?php echo $p['id']; ?>"
                                data-platform="<?php echo htmlspecialchars($p['platform']); ?>">Eliminar</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
// --- FUNCIONES HELPER ---
function copyToClipboard(button, text) {
    navigator.clipboard.writeText(text).then(() => {
        const originalText = button.textContent;
        button.textContent = '¡Copiado!';
        setTimeout(() => { button.textContent = originalText; }, 1500);
    }, (err) => {
        console.error('Error al copiar:', err);
        showNotification('Error al copiar texto.', 'error');
    });
}

function showNotification(message, type = 'success') {
    const container = document.querySelector('.notification-container');
    if (!container) return;
    const alertType = type === 'success' ? 'alert-success' : 'alert-danger';
    const notification = document.createElement('div');
    notification.className = `alert ${alertType} alert-dismissible fade show`;
    notification.setAttribute('role', 'alert');
    notification.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
    container.appendChild(notification);
    setTimeout(() => {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(notification);
        if (bsAlert) bsAlert.close();
    }, 5000);
}

// --- LÓGICA PRINCIPAL DE LA PÁGINA ---
document.addEventListener('DOMContentLoaded', function () {
    const tableBody = document.querySelector('.table tbody');
    if (!tableBody) return;

    // --- GESTIÓN DE LA BARRA DE EDICIÓN ---
    const editBar = document.getElementById('footer-edit-bar');
    const editForm = document.getElementById('edit-form');
    const cancelEditBtn = document.getElementById('cancel-edit-btn');

    // --- GESTIÓN DE LA BARRA DE ELIMINACIÓN ---
    const deleteBar = document.getElementById('footer-confirmation-bar');
    const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    const cancelDeleteBtn = document.getElementById('cancel-delete-btn');
    const confirmationText = document.getElementById('confirmation-text');
    let passwordIdToDelete = null;

    // Delegación de eventos en la tabla para los botones de Editar y Eliminar
    tableBody.addEventListener('click', function(event) {
        const button = event.target;

        // --- Lógica para el botón de EDITAR ---
        if (button.classList.contains('btn-edit-password')) {
            // Ocultar la barra de eliminar si estuviera visible
            deleteBar.classList.remove('show');
            
            // Rellenar el formulario de edición con los datos del botón
            editForm.querySelector('#edit-id-input').value = button.dataset.id;
            editForm.querySelector('#edit-platform-input').value = button.dataset.platform;
            editForm.querySelector('#edit-username-input').value = button.dataset.username;
            editForm.querySelector('#edit-code-input').value = button.dataset.code;
            editForm.querySelector('#edit-notes-input').value = button.dataset.notes;
            editForm.querySelector('#edit-password-input').value = ''; // Limpiar campo de nueva pass

            // Mostrar la barra de edición
            editBar.classList.add('show');
        }

        // --- Lógica para el botón de ELIMINAR ---
        if (button.classList.contains('btn-delete-password')) {
            // Ocultar la barra de edición si estuviera visible
            editBar.classList.remove('show');

            passwordIdToDelete = button.dataset.id;
            confirmationText.innerHTML = `¿Seguro que quieres eliminar la contraseña para <strong>${button.dataset.platform}</strong>?`;
            deleteBar.classList.add('show');
        }
    });

    // --- Event Listeners para los botones de las barras ---

    // Cancelar edición
    cancelEditBtn.addEventListener('click', () => editBar.classList.remove('show'));

    // Cancelar eliminación
    cancelDeleteBtn.addEventListener('click', () => {
        deleteBar.classList.remove('show');
        passwordIdToDelete = null;
    });

    // Enviar formulario de edición (AJAX)
    editForm.addEventListener('submit', function(event) {
        event.preventDefault();
        const formData = new FormData(this);
        const passwordId = formData.get('password_id');

        fetch('passwords.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const updated = data.updatedData;
                const row = document.getElementById(`password-row-${passwordId}`);
                if (row) {
                    // Actualizar celdas y atributos data-* del botón de editar
                    row.cells[0].textContent = updated.platform;
                    row.cells[1].querySelector('input').value = updated.username;
                    row.cells[2].querySelector('input').value = updated.password;
                    row.cells[3].querySelector('input').value = updated.code;
                    row.cells[4].querySelector('textarea').value = updated.notes;

                    const editButton = row.querySelector('.btn-edit-password');
                    Object.keys(updated).forEach(key => {
                        editButton.dataset[key] = updated[key];
                    });
                }
                editBar.classList.remove('show'); // Ocultar barra al éxito
            }
            showNotification(data.message, data.status);
        })
        .catch(error => {
            console.error('Error en la edición:', error);
            showNotification('Ocurrió un error de red.', 'error');
        });
    });

    // Confirmar eliminación (AJAX)
    confirmDeleteBtn.addEventListener('click', function() {
        if (!passwordIdToDelete) return;
        const formData = new FormData();
        formData.append('action', 'delete_password');
        formData.append('password_id', passwordIdToDelete);

        fetch('passwords.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const rowToRemove = document.getElementById(`password-row-${passwordIdToDelete}`);
                if (rowToRemove) {
                    rowToRemove.style.transition = 'opacity 0.5s ease';
                    rowToRemove.style.opacity = '0';
                    setTimeout(() => rowToRemove.remove(), 500);
                }
            }
            showNotification(data.message, data.status);
        })
        .catch(error => {
            console.error('Error en la eliminación:', error);
            showNotification('Ocurrió un error de red.', 'error');
        })
        .finally(() => {
            deleteBar.classList.remove('show');
            passwordIdToDelete = null;
        });
    });
});
</script>

<?php
include __DIR__ . '/footer.php';
?>
