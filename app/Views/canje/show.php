<?php
/**
 * Página de un cupón: a esto lleva el QR impreso.
 *
 * @var array  $coupon
 * @var string $role
 * @var array  $available
 * @var array  $history
 */
$status = $coupon['current_status'];
?>
<div class="mx-auto" style="max-width: 640px">

    <a class="text-secondary small text-decoration-none"
       href="<?= BASE_URL ?>/c/<?= urlencode($coupon['slug']) ?>">
        &larr; <?= htmlspecialchars($coupon['book_title']) ?>
    </a>

    <div class="card mt-3 mb-4">
        <div class="card-body">
            <div class="d-flex align-items-start gap-2 mb-3">
                <span class="badge text-bg-<?= StatusMachine::variant($status) ?> fs-6">
                    <?= htmlspecialchars(StatusMachine::label($status)) ?>
                </span>
                <code class="ms-auto text-secondary"><?= htmlspecialchars($coupon['code']) ?></code>
            </div>

            <h1 class="brand-hand display-5 mb-2"><?= htmlspecialchars($coupon['title']) ?></h1>
            <p class="lead text-secondary"><?= htmlspecialchars($coupon['description']) ?></p>

            <?php if ($coupon['tone'] !== '' || $coupon['duration'] !== ''): ?>
                <p class="small text-uppercase text-secondary mb-0">
                    <?= htmlspecialchars(trim($coupon['tone'] . ' · ' . $coupon['duration'], ' ·')) ?>
                </p>
            <?php endif; ?>
        </div>

        <?php if ($available): ?>
            <div class="card-footer d-flex flex-wrap gap-2">
                <form method="post" action="<?= BASE_URL ?>/cupon/<?= urlencode($coupon['code']) ?>"
                      class="d-flex flex-wrap gap-2 w-100">
                    <?= Csrf::field() ?>
                    <?php /* channel=qr deja constancia en el historial de que el
                             canje salió del cupón de papel y no de la web. */ ?>
                    <input type="hidden" name="channel" value="qr">
                    <?php foreach ($available as $to): ?>
                        <button type="submit" name="to" value="<?= $to ?>"
                                class="btn <?= $to === StatusMachine::FULFILLED
                                    ? 'btn-success'
                                    : ($to === StatusMachine::REQUESTED ? 'btn-accent' : 'btn-outline-secondary') ?>">
                            <?= htmlspecialchars(match ($to) {
                                StatusMachine::REQUESTED => 'Canjear este cupón',
                                StatusMachine::FULFILLED => 'Marcar como cumplido',
                                StatusMachine::DECLINED  => 'Ahora no puedo',
                                StatusMachine::CANCELLED => 'Cancelar la petición',
                                default                  => StatusMachine::label($to),
                            }) ?>
                        </button>
                    <?php endforeach; ?>
                </form>
            </div>
        <?php elseif ($status === StatusMachine::FULFILLED): ?>
            <div class="card-footer text-secondary small">
                Este cupón ya se cumplió. No hay vuelta atrás.
            </div>
        <?php elseif ($status === StatusMachine::REQUESTED && $role === 'recipient'): ?>
            <div class="card-footer text-secondary small">
                Ya está pedido. Te avisamos en cuanto lo confirme
                <?= htmlspecialchars($coupon['owner_name']) ?>.
            </div>
        <?php endif; ?>
    </div>

    <h2 class="h6 text-uppercase text-secondary mb-3">Historial</h2>

    <?php if (!$history): ?>
        <p class="text-secondary small">Todavía no ha pasado nada con este cupón.</p>
    <?php else: ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($history as $entry): ?>
                <li class="list-group-item d-flex flex-wrap align-items-center gap-2 px-0">
                    <span class="badge text-bg-<?= StatusMachine::variant($entry['status']) ?>">
                        <?= htmlspecialchars(StatusMachine::label($entry['status'])) ?>
                    </span>
                    <span class="small"><?= htmlspecialchars($entry['actor_name'] ?? 'Sistema') ?></span>
                    <span class="small text-secondary ms-auto">
                        <?= htmlspecialchars(match ($entry['channel']) {
                            'qr'       => 'escaneando el cupón',
                            'telegram' => 'desde Telegram',
                            'email'    => 'desde el correo',
                            'system'   => 'automático',
                            default    => 'desde la web',
                        }) ?>
                        · <?= htmlspecialchars(date('d/m/Y H:i', strtotime($entry['created_at']))) ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
