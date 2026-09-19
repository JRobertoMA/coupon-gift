<?php
/**
 * Panel de personalización de la vista de impresión.
 *
 * Lleva .no-print: nunca llega al papel. Los cambios reescriben las variables
 * CSS del <div id="theme-root"> en el navegador, igual que hacía el editor del
 * prototipo, y actualizan los parámetros de la URL para poder compartir una
 * combinación concreta.
 *
 * @var array $book
 * @var array $settings  valores efectivos (los de la cuponera o los de la URL)
 * @var bool  $showCover
 * @var bool  $showTone
 */
?>
<div class="print-toolbar no-print">
    <div class="pt-group pt-title">
        <a class="pt-back" href="<?= BASE_URL ?>/c/<?= urlencode($book['slug']) ?>">&larr;</a>
        <strong><?= htmlspecialchars($book['title']) ?></strong>
    </div>

    <?php /* A diferencia de paleta/estilo/tipografía, cambiar el tamaño RECARGA:
             cambia cuántos cupones caben por hoja y la orientación, y eso lo
             decide PHP al paginar. Es la misma regla que al añadir o borrar
             cupones. */ ?>
    <div class="pt-group">
        <label for="pt-size">Tamaño</label>
        <select id="pt-size" data-param="tamano" data-reload="1">
            <?php foreach (Design::SIZES as $key => $spec): ?>
                <option value="<?= htmlspecialchars($key) ?>"
                    <?= $key === $settings['size'] ? 'selected' : '' ?>
                    title="<?= htmlspecialchars($spec['hint']) ?>">
                    <?= htmlspecialchars($spec['label']) ?> · <?= (int) $spec['per_sheet'] ?>/hoja
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="pt-group">
        <label for="pt-style">Estilo</label>
        <select id="pt-style" data-param="estilo">
            <?php foreach (array_keys(Design::STYLES) as $name): ?>
                <option value="<?= htmlspecialchars($name) ?>"
                    <?= $name === $settings['style'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($name) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="pt-group">
        <label>Paleta</label>
        <div class="pt-palettes" id="pt-palettes">
            <?php foreach (Design::PALETTES as $i => $palette): ?>
                <button type="button"
                        class="pt-swatch<?= $i === $settings['palette'] ? ' is-active' : '' ?>"
                        data-palette="<?= $i ?>"
                        title="Paleta <?= $i + 1 ?>">
                    <?php foreach ($palette as $color): ?>
                        <span style="background: <?= htmlspecialchars($color) ?>"></span>
                    <?php endforeach; ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="pt-group">
        <label for="pt-typography">Tipografía</label>
        <select id="pt-typography" data-param="tipografia">
            <?php foreach (array_keys(Design::FONTS) as $name): ?>
                <option value="<?= htmlspecialchars($name) ?>"
                    <?= $name === $settings['typography'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($name) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="pt-group pt-checks">
        <label><input type="checkbox" id="pt-cover" <?= $showCover ? 'checked' : '' ?>> Portada</label>
        <label><input type="checkbox" id="pt-tone"  <?= $showTone  ? 'checked' : '' ?>> Tonos</label>
    </div>

    <div class="pt-group pt-actions">
        <button type="button" class="pt-btn" id="pt-print">Imprimir</button>
        <a class="pt-btn pt-btn--ghost" id="pt-save"
           href="<?= BASE_URL ?>/cuponeras/<?= (int) $book['id'] ?>/editar">Guardar combinación</a>
    </div>

    <p class="pt-hint">
        En Chrome, activa <strong>«Gráficos de fondo»</strong> en el diálogo de impresión o las hojas
        saldrán en blanco: todo el diseño son fondos.
        <?php if (!QrService::available()): ?>
            <br><strong>Faltan los QR:</strong> ejecuta <code>composer install</code> dentro del contenedor.
        <?php endif; ?>
    </p>
</div>
