<?php
/**
 * Ticket PEQUEÑO: 9,8 × 4,7 cm, 10 por hoja vertical (2 × 5). Diseño de tarjeta.
 *
 * Dos zonas, sin lateral derecho: el QR baja al talón, debajo del número, donde
 * le sobra sitio y puede ir MÁS grande que en el mediano (52 px). Es lo que
 * hace que un cupón de 4,7 cm siga escaneando. «Sin caducidad» desaparece: no
 * hay sitio y el pequeño no lo necesita.
 *
 * @var array $coupon
 * @var int   $total
 * @var bool  $showTone
 * @var bool  $useQr
 * @var array $sizeSpec
 */
$spent = StatusMachine::isSpent($coupon['current_status']);
$meta  = array_filter([$coupon['tone'], $coupon['duration']], fn ($v) => $v !== '');
?>
<div class="ticket ticket--sm<?= $spent ? ' ticket--spent' : '' ?>" <?= $spent ? 'data-stamp="Cumplido"' : '' ?>>

    <div class="tape tape--t"></div>

    <div class="ticket-stub">
        <div class="ticket-number"><?= (int) $coupon['position'] ?><span class="ticket-of">/<?= $total ?></span></div>
        <?php if ($useQr): ?>
            <div class="ticket-qr"><?= QrService::svg($coupon['code'], $sizeSpec['qr'], $sizeSpec['ec']) ?></div>
            <div class="ticket-code"><?= htmlspecialchars($coupon['code']) ?></div>
        <?php else: ?>
            <div class="ticket-seal">vale<br>ya</div>
        <?php endif; ?>
    </div>

    <div class="ticket-body">
        <?php if ($meta): ?>
            <div class="ticket-meta<?= $showTone ? '' : ' is-hidden' ?>">
                <?= htmlspecialchars(implode(' · ', $meta)) ?>
            </div>
        <?php endif; ?>

        <div class="ticket-title"><?= htmlspecialchars($coupon['title']) ?></div>
        <div class="ticket-rule"></div>
        <div class="ticket-desc"><?= htmlspecialchars($coupon['description']) ?></div>
    </div>
</div>
