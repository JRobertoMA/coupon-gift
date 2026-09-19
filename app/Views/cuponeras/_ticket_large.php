<?php
/**
 * Ticket GRANDE: el diseño del prototipo, al píxel. 19,3 × 7,9 cm, 3 por hoja.
 * No cambiar medidas aquí: ver la cuenta de los 299px en CLAUDE.md.
 *
 * @var array $coupon
 * @var int   $total
 * @var bool  $showTone
 * @var bool  $useQr
 * @var array $sizeSpec
 */
$spent = StatusMachine::isSpent($coupon['current_status']);
?>
<div class="ticket<?= $spent ? ' ticket--spent' : '' ?>" <?= $spent ? 'data-stamp="Cumplido"' : '' ?>>

    <div class="tape tape--t"></div>

    <div class="ticket-stub">
        <div class="ticket-number"><?= (int) $coupon['position'] ?></div>
        <?php /* «de N» se deriva del número real de cupones, no del literal
                 «de 20» del prototipo: así sigue siendo correcto al añadir o
                 borrar cupones. */ ?>
        <div class="ticket-of">de <?= $total ?></div>
    </div>

    <div class="ticket-body">
        <?php /* Se renderiza siempre y el panel la muestra u oculta sin recargar. */ ?>
        <?php if ($coupon['tone'] !== '' || $coupon['duration'] !== ''): ?>
            <div class="ticket-meta<?= $showTone ? '' : ' is-hidden' ?>">
                <?php if ($coupon['tone'] !== ''): ?>
                    <div class="ticket-tag"><?= htmlspecialchars($coupon['tone']) ?></div>
                <?php endif; ?>
                <?php if ($coupon['duration'] !== ''): ?>
                    <div><?= htmlspecialchars($coupon['duration']) ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="ticket-title"><?= htmlspecialchars($coupon['title']) ?></div>
        <div class="ticket-rule"></div>
        <div class="ticket-desc"><?= htmlspecialchars($coupon['description']) ?></div>
    </div>

    <div class="ticket-side">
        <?php if ($useQr): ?>
            <div class="ticket-qr"><?= QrService::svg($coupon['code'], $sizeSpec['qr'], $sizeSpec['ec']) ?></div>
            <div class="ticket-code"><?= htmlspecialchars($coupon['code']) ?></div>
        <?php else: ?>
            <div class="ticket-seal">vale<br>ya</div>
        <?php endif; ?>
        <div class="ticket-vertical">Sin caducidad</div>
    </div>
</div>
