<?php
require_once __DIR__ . '/database.php';

// 1. Proteger la página: solo usuarios logueados pueden acceder.
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

// 2. Lógica para procesar los formularios (Añadir Deuda o Añadir Transacción)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- Acción: Añadir una nueva deuda ---
    if ($action === 'add_debt') {
        $name = $_POST['name'] ?? '';
        $amount = $_POST['amount'] ?? 0;

        if (!empty($name) && is_numeric($amount) && $amount > 0) {
            $stmt = $pdo->prepare("INSERT INTO debts (user_id, name, initial_amount, current_balance) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$user_id, $name, $amount, $amount])) {
                $success = "Deuda '{$name}' añadida correctamente.";
            } else {
                $error = "Error al añadir la deuda.";
            }
        } else {
            $error = "Por favor, proporciona un nombre válido y un monto mayor a cero.";
        }
    }

    // --- Acción: Añadir una transacción (pago o aumento) ---
    if ($action === 'add_transaction') {
        $debt_id = $_POST['debt_id'] ?? 0;
        $type = $_POST['type'] ?? '';
        // Usamos filter_input para una validación más robusta del monto.
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
        $description = $_POST['description'] ?? '';

        // 1. Validar que todos los datos necesarios son correctos.
        if ($debt_id && ($type === 'payment' || $type === 'increase') && $amount && $amount > 0) {
            try {
                $pdo->beginTransaction();

                // 2. ¡Importante! Verificar que la deuda pertenece al usuario actual antes de hacer nada.
                // Esto previene que un usuario modifique deudas ajenas.
                $stmt = $pdo->prepare("SELECT id FROM debts WHERE id = ? AND user_id = ?");
                $stmt->execute([$debt_id, $user_id]);
                if (!$stmt->fetch()) {
                    // Si la consulta no devuelve nada, la deuda no existe o no es del usuario.
                    throw new Exception("Operación no permitida. La deuda no se encontró.");
                }

                // 3. Insertar la transacción
                $stmt = $pdo->prepare("INSERT INTO transactions (debt_id, type, amount, description) VALUES (?, ?, ?, ?)");
                $stmt->execute([$debt_id, $type, $amount, $description]);

                // 4. Actualizar el saldo actual de la deuda. Ya no es necesario el user_id aquí.
                $balance_change = ($type === 'payment') ? -$amount : +$amount;
                $stmt = $pdo->prepare("UPDATE debts SET current_balance = current_balance + ? WHERE id = ?");
                $stmt->execute([$balance_change, $debt_id]);

                $pdo->commit();
                $success = "Movimiento registrado correctamente.";

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error al registrar la transacción: " . $e->getMessage();
            }
        } else {
            $error = "Datos de transacción inválidos. Asegúrate de que el monto sea un número positivo.";
        }
    }

    // --- Acción: Eliminar una transacción ---
    if ($action === 'delete_transaction') {
        $transaction_id = $_POST['transaction_id'] ?? 0;
        $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $response = ['status' => 'error', 'message' => 'Error al procesar la solicitud.'];

        if ($transaction_id) {
            try {
                $pdo->beginTransaction();

                // 1. Obtener los detalles de la transacción y verificar que pertenece al usuario.
                $stmt = $pdo->prepare(
                    "SELECT t.id, t.debt_id, t.type, t.amount 
                     FROM transactions t
                     JOIN debts d ON t.debt_id = d.id
                     WHERE t.id = ? AND d.user_id = ?"
                );
                $stmt->execute([$transaction_id, $user_id]);
                $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($transaction) {
                    // 2. Revertir el monto en el saldo de la deuda.
                    // Si se borra un 'pago', el saldo aumenta. Si se borra un 'cargo', el saldo disminuye.
                    $balance_change = ($transaction['type'] === 'payment') ? +$transaction['amount'] : -$transaction['amount'];
                    $stmt = $pdo->prepare("UPDATE debts SET current_balance = current_balance + ? WHERE id = ?");
                    $stmt->execute([$balance_change, $transaction['debt_id']]);

                    // 3. Eliminar la transacción.
                    $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ?");
                    $stmt->execute([$transaction_id]);

                    $pdo->commit();
                    $response = [
                        'status' => 'success', 
                        'message' => 'Movimiento eliminado correctamente.',
                        'debt_id' => $transaction['debt_id']
                    ];

                } else {
                    throw new Exception("Movimiento no encontrado o no tienes permiso para eliminarlo.");
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $response['message'] = $e->getMessage();
            }
        } else {
            $response['message'] = 'ID de movimiento no válido.';
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

    // --- Acción: Actualizar una deuda ---
    if ($action === 'update_debt') {
        $debt_id = $_POST['debt_id'] ?? 0;
        $name = $_POST['name'] ?? '';
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
        $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $response = ['status' => 'error', 'message' => 'Datos inválidos.'];

        if ($debt_id && !empty($name) && $amount !== false && $amount > 0) {
            // Recalcular el saldo actual basado en la diferencia del monto inicial
            $stmt = $pdo->prepare("SELECT initial_amount, current_balance FROM debts WHERE id = ? AND user_id = ?");
            $stmt->execute([$debt_id, $user_id]);
            $debt = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($debt) {
                $difference = $amount - $debt['initial_amount'];
                $new_balance = $debt['current_balance'] + $difference;

                $stmt = $pdo->prepare("UPDATE debts SET name = ?, initial_amount = ?, current_balance = ? WHERE id = ? AND user_id = ?");
                if ($stmt->execute([$name, $amount, $new_balance, $debt_id, $user_id])) {
                    $response = [
                        'status' => 'success',
                        'message' => 'Deuda actualizada.',
                        'updatedData' => ['id' => $debt_id, 'name' => $name, 'initial_amount' => $amount, 'current_balance' => $new_balance]
                    ];
                } else {
                    $response['message'] = 'Error al actualizar la deuda.';
                }
            } else {
                $response['message'] = 'Deuda no encontrada.';
            }
        }
        if ($is_ajax) { header('Content-Type: application/json'); echo json_encode($response); exit; }
    }

    // --- Acción: Eliminar una deuda ---
    if ($action === 'delete_debt') {
        $debt_id = $_POST['debt_id'] ?? 0;
        $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $response = ['status' => 'error', 'message' => 'ID de deuda no válido.'];

        if ($debt_id) {
            // La eliminación en cascada debería borrar las transacciones asociadas
            $stmt = $pdo->prepare("DELETE FROM debts WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$debt_id, $user_id]) && $stmt->rowCount() > 0) {
                $response = ['status' => 'success', 'message' => 'Deuda eliminada correctamente.'];
            } else {
                $response['message'] = 'Error al eliminar la deuda o no se encontró.';
            }
        }
        if ($is_ajax) { header('Content-Type: application/json'); echo json_encode($response); exit; }
    }
}

// 3. Obtener todas las deudas del usuario para mostrarlas
$stmt = $pdo->prepare("SELECT id, name, initial_amount, current_balance, created_at FROM debts WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$debts = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/header.php';
?>

<h1>Dashboard de Finanzas</h1>

<!-- Formulario para añadir una nueva deuda -->
<div class="p-4 mb-4" style="background-color: rgba(0,0,0,0.2); border: 1px solid #00ff50;">
    <h2>Añadir Nueva Deuda</h2>
    <form action="dashboard.php" method="POST">
        <input type="hidden" name="action" value="add_debt">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="name" class="form-label">Nombre de la Deuda (Ej: Tarjeta de Crédito)</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="col-md-4 mb-3">
                <label for="amount" class="form-label">Monto Inicial</label>
                <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
            </div>
            <div class="col-md-2 d-flex align-items-end mb-3">
                <button type="submit" class="btn btn-primary w-100">Añadir</button>
            </div>
        </div>
    </form>
</div>

<!-- Listado de deudas existentes -->
<h2>Mis Deudas</h2>
<div id="debts-container">
<?php if (empty($debts)): ?>
    <p>No tienes ninguna deuda registrada. ¡Empieza añadiendo una!</p>
<?php else: ?>
    <?php foreach ($debts as $debt): ?>
        <div class="p-3 mb-3" id="debt-block-<?php echo $debt['id']; ?>" style="background-color: rgba(0,0,0,0.2); border: 1px solid rgba(0, 255, 80, 0.3);">
            <div class="d-flex justify-content-between align-items-center">
                <h4 id="debt-name-<?php echo $debt['id']; ?>"><?php echo htmlspecialchars($debt['name']); ?></h4>
                <div>
                    <button class="btn btn-sm btn-info btn-edit-debt"
                        data-id="<?php echo $debt['id']; ?>"
                        data-name="<?php echo htmlspecialchars($debt['name']); ?>"
                        data-amount="<?php echo htmlspecialchars($debt['initial_amount']); ?>">
                        Editar
                    </button>
                    <button class="btn btn-sm btn-danger btn-delete-debt"
                        data-id="<?php echo $debt['id']; ?>"
                        data-name="<?php echo htmlspecialchars($debt['name']); ?>">
                        Eliminar
                    </button>
                    <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#history-<?php echo $debt['id']; ?>" aria-expanded="false" aria-controls="history-<?php echo $debt['id']; ?>">
                        Ver Historial
                    </button>
                </div>
            </div>
            <p class="mb-2">
                Saldo Actual: <strong id="debt-balance-<?php echo $debt['id']; ?>" style="font-size: 1.2em; color: <?php echo $debt['current_balance'] > 0 ? '#ff4d4d' : '#00ff50'; ?>;">$<?php echo number_format($debt['current_balance'], 2); ?></strong>
                <small class="text-muted ms-2">(Inicial: <span id="debt-initial-<?php echo $debt['id']; ?>">$<?php echo number_format($debt['initial_amount'], 2); ?></span>)</small>
            </p>
            
            <!-- Formulario para añadir pago o aumento -->
            <form action="dashboard.php" method="POST" class="row g-3 align-items-end mb-3">
                <input type="hidden" name="action" value="add_transaction">
                <input type="hidden" name="debt_id" value="<?php echo $debt['id']; ?>">
                <div class="col-auto"><select name="type" class="form-select"><option value="payment">Registrar Pago</option><option value="increase">Añadir Cargo</option></select></div>
                <div class="col-auto"><input type="number" step="0.01" name="amount" class="form-control" placeholder="Monto" required></div>
                <div class="col-auto"><input type="text" name="description" class="form-control" placeholder="Descripción (Opcional)"></div>
                <div class="col-auto"><button type="submit" class="btn btn-primary">Registrar</button></div>
            </form>

            <!-- Historial de transacciones colapsable -->
            <div class="collapse" id="history-<?php echo $debt['id']; ?>">
                <div class="p-3" style="background-color: rgba(0,0,0,0.3);">
                    <h5>Historial de Movimientos</h5>
                    <?php
                        $trans_stmt = $pdo->prepare("SELECT id, type, amount, description, transaction_date FROM transactions WHERE debt_id = ? ORDER BY transaction_date DESC");
                        $trans_stmt->execute([$debt['id']]);
                        $transactions = $trans_stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <?php if (empty($transactions)): ?>
                        <p class="small">No hay movimientos registrados para esta deuda.</p>
                    <?php else: ?>
                        <table class="table table-sm table-borderless" style="color: #e0e0e0;">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Monto</th>
                                    <th>Descripción</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($transactions as $tr): ?>
                                <tr id="transaction-row-<?php echo $tr['id']; ?>">
                                    <td><?php echo (new DateTime($tr['transaction_date']))->format('d/m/Y'); ?></td>
                                    <td><span class="badge" style="background-color: <?php echo $tr['type'] === 'payment' ? '#00ff50' : '#ff4d4d'; ?>; color: #1a021a;"><?php echo $tr['type'] === 'payment' ? 'PAGO' : 'CARGO'; ?></span></td>
                                    <td>$<?php echo number_format($tr['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($tr['description']); ?></td>
                                    <td>
                                        <button class="btn btn-xs btn-danger btn-delete-transaction" data-id="<?php echo $tr['id']; ?>" title="Eliminar movimiento">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/><path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>

<?php
include __DIR__ . '/footer.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const debtsContainer = document.getElementById('debts-container');
    if (!debtsContainer) return;

    // --- GESTIÓN DE BARRAS DEL FOOTER ---
    const debtEditBar = document.getElementById('footer-debt-edit-bar');
    const debtEditForm = document.getElementById('debt-edit-form');
    const cancelDebtEditBtn = document.getElementById('cancel-debt-edit-btn');
    
    const deleteConfirmBar = document.getElementById('footer-confirmation-bar');
    const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    const cancelDeleteBtn = document.getElementById('cancel-delete-btn');
    const confirmationText = document.getElementById('confirmation-text');

    let itemToDelete = { id: null, type: null };

    // Delegación de eventos en el contenedor de deudas
    debtsContainer.addEventListener('click', function(event) {
        const button = event.target.closest('button');
        if (!button) return;

        // --- Eliminar Transacción (Mostrar Barra) ---
        if (button.classList.contains('btn-delete-transaction')) {
            itemToDelete = { id: button.dataset.id, type: 'transaction' };
            confirmationText.innerHTML = `¿Seguro que quieres eliminar este movimiento?`;
            deleteConfirmBar.classList.add('show');
            debtEditBar.classList.remove('show');
        }

        // --- Editar Deuda (Mostrar Barra) ---
        if (button.classList.contains('btn-edit-debt')) {
            debtEditForm.querySelector('#debt-edit-id-input').value = button.dataset.id;
            debtEditForm.querySelector('#debt-edit-name-input').value = button.dataset.name;
            debtEditForm.querySelector('#debt-edit-amount-input').value = button.dataset.amount;
            debtEditBar.classList.add('show');
            deleteConfirmBar.classList.remove('show');
        }

        // --- Eliminar Deuda (Mostrar Barra) ---
        if (button.classList.contains('btn-delete-debt')) {
            itemToDelete = { id: button.dataset.id, type: 'debt' };
            confirmationText.innerHTML = `¿Seguro que quieres eliminar la deuda <strong>${button.dataset.name}</strong>? Se borrarán todos sus movimientos.`;
            deleteConfirmBar.classList.add('show');
            debtEditBar.classList.remove('show');
        }
    });

    // --- MANEJO DE FORMULARIOS Y BOTONES DE LAS BARRAS ---

    cancelDebtEditBtn.addEventListener('click', () => debtEditBar.classList.remove('show'));

    debtEditForm.addEventListener('submit', function(event) {
        event.preventDefault();
        const formData = new FormData(this);
        fetch('dashboard.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
    });

    cancelDeleteBtn.addEventListener('click', () => {
        deleteConfirmBar.classList.remove('show');
        itemToDelete = { id: null, type: null };
    });

    confirmDeleteBtn.addEventListener('click', function() {
        if (!itemToDelete.id) return;

        const formData = new FormData();
        if (itemToDelete.type === 'transaction') {
            formData.append('action', 'delete_transaction');
            formData.append('transaction_id', itemToDelete.id);
        } else {
            formData.append('action', 'delete_debt');
            formData.append('debt_id', itemToDelete.id);
        }

        fetch('dashboard.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
    });
});
</script>
