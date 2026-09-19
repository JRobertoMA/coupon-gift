<?php
/**
 * El enlace del correo ya no vale: usado, caducado, o el cupón cambió de estado
 * por otra vía (Telegram o la web).
 *
 * @var string|null $reason
 */
?>
<div class="auth-card text-center">
    <div class="display-4 mb-2">🔗</div>
    <h1 class="h4 mb-2">Este enlace ya no sirve</h1>
    <p class="text-secondary mb-4">
        <?php if (!empty($reason)): ?>
            <?= htmlspecialchars($reason) ?>
        <?php else: ?>
            Puede que ya lo hayas usado, que haya caducado, o que el cupón se resolviera
            desde Telegram o desde la web.
        <?php endif; ?>
    </p>
    <a class="btn btn-accent" href="<?= BASE_URL ?>/entrar">Entrar</a>
</div>
