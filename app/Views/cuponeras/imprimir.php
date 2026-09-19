<?php
/**
 * El documento imprimible: recreación del diseño de Claude Design.
 *
 * Paginación explícita — una <section class="sheet"> por hoja. Cuántos cupones
 * lleva cada hoja lo decide el tamaño (Design::SIZES): 3 en grande, 6 en
 * mediano apaisado, 10 en pequeño. Nada de saltos de página en el contenido:
 * los pone .sheet en sheet.css.
 *
 * El ticket de cada tamaño vive en su parcial (_ticket_{large,medium,small}.php).
 * En mediano y pequeño los tickets van dentro de una rejilla de 2 columnas.
 *
 * @var array  $book
 * @var array  $sheets    cupones agrupados según el tamaño
 * @var int    $total
 * @var bool   $showCover
 * @var bool   $showTone
 * @var bool   $useQr
 * @var string $size      large | medium | small
 * @var array  $sizeSpec
 */
$totalSheets = count($sheets);
$partial     = BASE_PATH . '/app/Views/cuponeras/_ticket_' . $size . '.php';
$grid        = $size !== 'large';
?>

<?php /* La portada se renderiza siempre; el panel la oculta con .is-hidden para
         poder quitarla y ponerla sin recargar. Si viene desactivada de la
         cuponera o de la URL, nace ya oculta. La misma maqueta sirve en vertical
         y en apaisado: está centrada y le sobra sitio. */ ?>
<section class="sheet sheet--cover<?= $showCover ? '' : ' is-hidden' ?>">
    <div class="page page--cover">
        <div class="cover-card">
            <div class="tape tape--a"></div>
            <div class="tape tape--b"></div>

            <?php /* Todos los textos son opcionales: lo vacío no se pinta, para
                     que no queden huecos ni un <h1> sin nada dentro. La regla
                     solo aparece si hay algo encima Y algo debajo. */ ?>
            <?php
            $eyebrow = trim((string) $book['cover_eyebrow']);
            $ctitle  = trim((string) $book['cover_title']);
            $accent  = trim((string) $book['cover_accent']);
            $note    = trim((string) $book['cover_note']);
            $hasHead = $eyebrow !== '' || $ctitle !== '' || $accent !== '';
            ?>

            <?php if ($eyebrow !== ''): ?>
                <div class="cover-eyebrow"><?= htmlspecialchars($eyebrow) ?></div>
            <?php endif; ?>

            <?php if ($ctitle !== '' || $accent !== ''): ?>
                <h1 class="cover-title">
                    <?php if ($ctitle !== ''): ?><?= htmlspecialchars($ctitle) ?><?php endif; ?>
                    <?php if ($ctitle !== '' && $accent !== ''): ?><br><?php endif; ?>
                    <?php if ($accent !== ''): ?><span class="accent"><?= htmlspecialchars($accent) ?></span><?php endif; ?>
                </h1>
            <?php endif; ?>

            <?php if ($hasHead && $note !== ''): ?>
                <div class="cover-rule"></div>
            <?php endif; ?>

            <?php if ($note !== ''): ?>
                <p class="cover-note"><?= htmlspecialchars($note) ?></p>
            <?php endif; ?>

            <div class="cover-fields">
                <div>
                    <div class="field-label">Para</div>
                    <div class="field-value"><?= htmlspecialchars($book['recipient_name']) ?></div>
                </div>
                <div>
                    <div class="field-label">De</div>
                    <div class="field-value"><?= htmlspecialchars($book['owner_name']) ?></div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php foreach ($sheets as $sheetIndex => $sheetCoupons): ?>
<section class="sheet">
    <div class="page page--coupons page--coupons-<?= htmlspecialchars($size) ?>">
        <div class="sheet-head">
            <div>Recorta por el borde de puntos</div>
            <div>Hoja <?= $sheetIndex + 1 ?> de <?= $totalSheets ?></div>
        </div>

        <?php if ($grid): ?><div class="ticket-grid"><?php endif; ?>

        <?php foreach ($sheetCoupons as $coupon): ?>
            <?php require $partial; ?>
        <?php endforeach; ?>

        <?php if ($grid): ?></div><?php endif; ?>
    </div>
</section>
<?php endforeach; ?>

<?php if ($totalSheets === 0): ?>
<section class="sheet">
    <div class="page page--cover">
        <div class="cover-card">
            <div class="cover-eyebrow">Cuponera vacía</div>
            <h1 class="cover-title">Todavía no<br><span class="accent">hay cupones</span></h1>
            <p class="cover-note">Añade cupones desde la pantalla de edición y vuelve a imprimir.</p>
        </div>
    </div>
</section>
<?php endif; ?>
