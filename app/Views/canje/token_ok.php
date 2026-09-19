<?php
/**
 * Confirmación hecha desde un enlace del correo. No hay sesión aquí.
 *
 * @var array  $coupon
 * @var string $status
 */
?>
<div class="auth-card text-center">
    <div class="display-4 mb-2"><?= $status === StatusMachine::FULFILLED ? '✅' : '✖️' ?></div>
    <h1 class="h4 mb-2"><?= htmlspecialchars(StatusMachine::label($status)) ?></h1>
    <p class="text-secondary mb-4">
        Has marcado «<?= htmlspecialchars($coupon['title']) ?>» como
        <strong><?= htmlspecialchars(mb_strtolower(StatusMachine::label($status))) ?></strong>.
        Ya se lo hemos dicho a <?= htmlspecialchars($coupon['recipient_name']) ?>.
    </p>
    <a class="btn btn-accent" href="<?= BASE_URL ?>/c/<?= urlencode($coupon['slug']) ?>">
        Ver la cuponera
    </a>
</div>
