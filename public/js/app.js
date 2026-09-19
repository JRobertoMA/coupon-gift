/* ============================================================================
   Comportamiento compartido de la interfaz.
   ========================================================================= */
(function () {
    'use strict';

    // ── Toasts ───────────────────────────────────────────────────────────────
    // Los mensajes flash se muestran como Toast auto-descartable.
    document.querySelectorAll('.toast').forEach(function (el) {
        new bootstrap.Toast(el).show();
    });

    // ── Modal de confirmación compartido ─────────────────────────────────────
    // Un único #confirmModal poblado desde los data-* del botón que lo abre,
    // en lugar de confirm() nativo.
    var confirmModal = document.getElementById('confirmModal');
    if (confirmModal) {
        confirmModal.addEventListener('show.bs.modal', function (event) {
            var trigger = event.relatedTarget;
            if (!trigger) return;

            var title   = trigger.dataset.title   || 'Confirmar';
            var body    = trigger.dataset.body    || '¿Seguro?';
            var url     = trigger.dataset.url     || '#';
            var variant = trigger.dataset.variant || 'danger';

            confirmModal.querySelector('.modal-title').textContent = title;
            confirmModal.querySelector('.modal-body').textContent  = body;
            confirmModal.querySelector('#confirmForm').action      = url;

            var submit = confirmModal.querySelector('#confirmSubmit');
            submit.className = 'btn btn-' + variant;
            submit.textContent = title;
        });
    }

    // ── Copiar al portapapeles ───────────────────────────────────────────────
    document.querySelectorAll('[data-copy-button]').forEach(function (button) {
        button.addEventListener('click', function () {
            var text = this.dataset.copyButton;
            var self = this;

            // navigator.clipboard solo existe en contextos seguros (HTTPS o
            // localhost); en LAN por HTTP hay que recurrir al textarea.
            var done = function () {
                var original = self.textContent;
                self.textContent = 'Copiado';
                setTimeout(function () { self.textContent = original; }, 1600);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(done);
                return;
            }

            var helper = document.createElement('textarea');
            helper.value = text;
            helper.setAttribute('readonly', '');
            helper.style.position = 'fixed';
            helper.style.opacity = '0';
            document.body.appendChild(helper);
            helper.select();
            try { document.execCommand('copy'); done(); } catch (e) { /* sin copia */ }
            document.body.removeChild(helper);
        });
    });

    // ── Selector de paleta ───────────────────────────────────────────────────
    document.querySelectorAll('.palette-radio input').forEach(function (input) {
        input.addEventListener('change', function () {
            document.querySelectorAll('.palette-radio').forEach(function (label) {
                label.classList.toggle('is-active', label.contains(input) && input.checked);
            });
        });
    });
})();
