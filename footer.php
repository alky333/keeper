</main> <!-- Cierra el .container del header -->

<footer class="app-footer">
    <div class="container text-center">
        &copy; <?php echo date('Y'); ?> Keeper | All Systems Operational
    </div>
</footer>

<!-- Barra de confirmación de eliminación (usada por passwords.php) -->
<div id="footer-confirmation-bar" class="footer-confirmation">
    <div class="container d-flex justify-content-between align-items-center">
        <span id="confirmation-text"></span>
        <div>
            <button id="confirm-delete-btn" class="btn btn-danger">Confirmar Eliminación</button>
            <button id="cancel-delete-btn" class="btn btn-secondary">Cancelar</button>
        </div>
    </div>
</div>

<!-- Barra de edición en el footer -->
<div id="footer-edit-bar" class="footer-confirmation" style="border-top-color: #0dcaf0;">
    <div class="container">
        <form id="edit-form">
            <input type="hidden" name="action" value="update_password">
            <input type="hidden" id="edit-id-input" name="password_id">
            <div class="row align-items-end">
                <div class="col-md-2"><label for="edit-platform-input" class="form-label">Plataforma</label><input type="text" id="edit-platform-input" name="platform" class="form-control form-control-sm"></div>
                <div class="col-md-2"><label for="edit-username-input" class="form-label">Usuario</label><input type="text" id="edit-username-input" name="username" class="form-control form-control-sm"></div>
                <div class="col-md-2"><label for="edit-password-input" class="form-label">Nueva Pass</label><input type="password" id="edit-password-input" name="password" class="form-control form-control-sm" placeholder="No cambiar"></div>
                <div class="col-md-2"><label for="edit-code-input" class="form-label">Cód. 2FA</label><input type="text" id="edit-code-input" name="security_code" class="form-control form-control-sm"></div>
                <div class="col-md-2"><label for="edit-notes-input" class="form-label">Notas</label><input type="text" id="edit-notes-input" name="notes" class="form-control form-control-sm"></div>
                <div class="col-md-2 d-flex justify-content-end">
                    <button type="submit" class="btn btn-info btn-sm me-2">Guardar</button>
                    <button type="button" id="cancel-edit-btn" class="btn btn-secondary btn-sm">Cancelar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Barra de edición de Deudas en el footer -->
<div id="footer-debt-edit-bar" class="footer-confirmation" style="border-top-color: #0dcaf0;">
    <div class="container">
        <form id="debt-edit-form" class="row align-items-end">
            <input type="hidden" name="action" value="update_debt">
            <input type="hidden" id="debt-edit-id-input" name="debt_id">
            <div class="col-md-5">
                <label for="debt-edit-name-input" class="form-label">Nuevo Nombre de la Deuda</label>
                <input type="text" id="debt-edit-name-input" name="name" class="form-control form-control-sm">
            </div>
            <div class="col-md-4">
                <label for="debt-edit-amount-input" class="form-label">Nuevo Monto Inicial</label>
                <input type="number" step="0.01" id="debt-edit-amount-input" name="amount" class="form-control form-control-sm">
            </div>
            <div class="col-md-3 d-flex justify-content-end">
                <button type="submit" class="btn btn-info btn-sm me-2">Guardar Cambios</button>
                <button type="button" id="cancel-debt-edit-btn" class="btn btn-secondary btn-sm">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Tu JS personalizado (si tienes uno) -->
<!-- <script src="<?php echo BASE_URL; ?>/assets/js/script.js"></script> -->
<script src="<?php echo BASE_URL; ?>/matrix.js"></script>
<script>
// Script para auto-cerrar las notificaciones flotantes
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.notification-container .alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            // Usamos el método de Bootstrap para cerrar el alert con animación
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000); // La notificación desaparece después de 5 segundos
    });
});
</script>
</body>
</html>
