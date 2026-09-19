<?php
/**
 * El enlace fijo de la cuponera.
 *
 * Reutiliza los tokens del documento impreso (--card, --line, --acc…) para que
 * la web y el papel se vean como la misma cosa.
 *
 * @var array  $book
 * @var array  $coupons
 * @var bool   $isOwner
 * @var string $role
 * @var string $themeStyle
 */
$fixedLink = APP_URL . '/c/' . $book['slug'];
?>
<div class="d-flex flex-wrap align-items-start gap-3 mb-2">
    <div class="me-auto">
        <h1 class="brand-hand mb-1"><?= htmlspecialchars($book['title']) ?></h1>
        <p class="text-secondary mb-0">
            <?php if ($isOwner): ?>
                Para <strong><?= htmlspecialchars($book['recipient_name']) ?></strong>
            <?php else: ?>
                De <strong><?= htmlspecialchars($book['owner_name']) ?></strong>
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary"
           href="<?= BASE_URL ?>/c/<?= urlencode($book['slug']) ?>/imprimir">Imprimir</a>
        <?php if ($isOwner): ?>
            <a class="btn btn-outline-secondary"
               href="<?= BASE_URL ?>/cuponeras/<?= (int) $book['id'] ?>/editar">Editar</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($isOwner): ?>
    <div class="fixed-link mb-4">
        <span class="text-secondary small">Enlace fijo</span>
        <code data-copy="<?= htmlspecialchars($fixedLink) ?>"><?= htmlspecialchars($fixedLink) ?></code>
        <button type="button" class="btn btn-sm btn-outline-secondary"
                data-copy-button="<?= htmlspecialchars($fixedLink) ?>">Copiar</button>
    </div>
<?php endif; ?>

<?php if ((int) $book['is_draft'] === 1): ?>
    <div class="alert alert-warning">
        Esta cuponera es un <strong>borrador</strong>: solo la ves tú.
        Quita la marca en «Editar» para que <?= htmlspecialchars($book['recipient_name']) ?> pueda abrirla.
    </div>
<?php endif; ?>

<?php if (!$coupons): ?>
    <div class="empty-state">
        <p class="fs-5 mb-2">Esta cuponera no tiene cupones todavía.</p>
        <?php if ($isOwner): ?>
            <a class="btn btn-accent" href="<?= BASE_URL ?>/cuponeras/<?= (int) $book['id'] ?>/editar">
                Añadir cupones
            </a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="row g-3" id="theme-root" style="<?= htmlspecialchars($themeStyle) ?>">
        <?php foreach ($coupons as $coupon): ?>
            <?php
            $status    = $coupon['current_status'];
            $spent     = StatusMachine::isSpent($status);
            $available = StatusMachine::availableFor($status, $role);
            ?>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="coupon-card<?= $spent ? ' is-spent' : '' ?>">
                    <div class="coupon-card__head">
                        <span class="coupon-card__number"><?= (int) $coupon['position'] ?></span>
                        <span class="badge text-bg-<?= StatusMachine::variant($status) ?>">
                            <?= htmlspecialchars(StatusMachine::label($status)) ?>
                        </span>
                    </div>

                    <div class="coupon-card__title"><?= htmlspecialchars($coupon['title']) ?></div>
                    <div class="coupon-card__rule"></div>
                    <p class="coupon-card__desc"><?= htmlspecialchars($coupon['description']) ?></p>

                    <?php if ($coupon['tone'] !== '' || $coupon['duration'] !== ''): ?>
                        <div class="coupon-card__meta">
                            <?php if ($coupon['tone'] !== ''): ?>
                                <span class="coupon-card__tag"><?= htmlspecialchars($coupon['tone']) ?></span>
                            <?php endif; ?>
                            <?php if ($coupon['duration'] !== ''): ?>
                                <span><?= htmlspecialchars($coupon['duration']) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="coupon-card__foot">
                        <a class="coupon-card__code" href="<?= BASE_URL ?>/cupon/<?= urlencode($coupon['code']) ?>">
                            <?= htmlspecialchars($coupon['code']) ?>
                        </a>

                        <?php if ($available): ?>
                            <form method="post"
                                  action="<?= BASE_URL ?>/cupon/<?= urlencode($coupon['code']) ?>"
                                  class="d-flex gap-2">
                                <?= Csrf::field() ?>
                                <?php foreach ($available as $to): ?>
                                    <button type="submit" name="to" value="<?= $to ?>"
                                            class="btn btn-sm <?= $to === StatusMachine::FULFILLED
                                                ? 'btn-success'
                                                : ($to === StatusMachine::REQUESTED ? 'btn-accent' : 'btn-outline-secondary') ?>">
                                        <?= htmlspecialchars(match ($to) {
                                            StatusMachine::REQUESTED => 'Canjear',
                                            StatusMachine::FULFILLED => 'Cumplido',
                                            StatusMachine::DECLINED  => 'Ahora no',
                                            StatusMachine::CANCELLED => 'Cancelar',
                                            default                  => StatusMachine::label($to),
                                        }) ?>
                                    </button>
                                <?php endforeach; ?>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
