<?php
/**
 * Alta y edición de una cuponera.
 *
 * La cuponera nace vacía. Al editar se listan los cupones y hay un botón para
 * cargar los 20 del diseño si se quiere partir de ellos.
 *
 * @var array|null $book
 * @var array      $people
 * @var array      $coupons  solo al editar
 */
$isEdit  = $book !== null;
$action  = $isEdit
    ? BASE_URL . '/cuponeras/' . (int) $book['id'] . '/editar'
    : BASE_URL . '/cuponeras/nueva';
$coupons = $coupons ?? [];

/** Valor guardado de un texto de portada. Al crear, vacío: el ejemplo va de placeholder. */
$value = fn (string $key): string => $isEdit ? (string) $book[$key] : '';

/** El texto del diseño, como placeholder. */
$hint = fn (string $key): string => Design::DEFAULT_COVER[str_replace('cover_', '', $key)] ?? '';

$currentStyle = $isEdit
    ? Design::style($_GET['estilo'] ?? $book['style'])
    : Design::style($_GET['estilo'] ?? null);
$currentType = $isEdit
    ? Design::typography($_GET['tipografia'] ?? $book['typography'])
    : Design::typography($_GET['tipografia'] ?? null);
$currentPalette = $isEdit
    ? Design::palette($_GET['paleta'] ?? $book['palette'])
    : Design::palette($_GET['paleta'] ?? null);
$currentSize = $isEdit
    ? Design::size($_GET['tamano'] ?? $book['ticket_size'])
    : Design::size($_GET['tamano'] ?? null);

$checked = function (string $key, bool $default = true) use ($book, $isEdit): bool {
    if (!$isEdit) return $default;
    return (int) $book[$key] === 1;
};
?>

<div class="d-flex flex-wrap align-items-center gap-3 mb-4">
    <h1 class="brand-hand mb-0 me-auto">
        <?= $isEdit ? 'Editar cuponera' : 'Nueva cuponera' ?>
    </h1>
    <?php if ($isEdit): ?>
        <a class="btn btn-outline-secondary"
           href="<?= BASE_URL ?>/c/<?= urlencode($book['slug']) ?>/imprimir">Ver e imprimir</a>
    <?php endif; ?>
    <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/cuponeras">Volver</a>
</div>

<form method="post" action="<?= htmlspecialchars($action) ?>" class="mb-5">
    <?= Csrf::field() ?>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h6 text-uppercase text-secondary mb-3">Lo básico</h2>

            <div class="row g-3">
                <div class="col-12 col-md-7">
                    <label class="form-label" for="title">Título</label>
                    <input type="text" class="form-control" id="title" name="title" maxlength="160"
                           value="<?= htmlspecialchars($isEdit ? $book['title'] : '') ?>"
                           placeholder="Cuponera de cumpleaños" required>
                </div>

                <div class="col-12 col-md-5">
                    <label class="form-label" for="recipient_id">Para quién</label>
                    <select class="form-select" id="recipient_id" name="recipient_id" required>
                        <option value="">Elige…</option>
                        <?php foreach ($people as $person): ?>
                            <option value="<?= (int) $person['id'] ?>"
                                <?= $isEdit && (int) $book['recipient_id'] === (int) $person['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($person['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$people): ?>
                        <div class="form-text text-warning">
                            No hay nadie más dado de alta. Crea la otra cuenta con
                            <code>cli/user.php</code>.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h6 text-uppercase text-secondary mb-3">Aspecto</h2>

            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="style">Estilo</label>
                    <select class="form-select" id="style" name="style">
                        <?php foreach (array_keys(Design::STYLES) as $name): ?>
                            <option value="<?= htmlspecialchars($name) ?>"
                                <?= $name === $currentStyle ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label" for="typography">Tipografía</label>
                    <select class="form-select" id="typography" name="typography">
                        <?php foreach (array_keys(Design::FONTS) as $name): ?>
                            <option value="<?= htmlspecialchars($name) ?>"
                                <?= $name === $currentType ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label d-block">Paleta</label>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach (Design::PALETTES as $i => $palette): ?>
                            <label class="palette-radio<?= $i === $currentPalette ? ' is-active' : '' ?>">
                                <input type="radio" name="palette" value="<?= $i ?>"
                                    <?= $i === $currentPalette ? 'checked' : '' ?>>
                                <?php foreach ($palette as $color): ?>
                                    <span style="background: <?= htmlspecialchars($color) ?>"></span>
                                <?php endforeach; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="ticket_size">Tamaño del cupón</label>
                    <select class="form-select" id="ticket_size" name="ticket_size">
                        <?php foreach (Design::SIZES as $key => $spec): ?>
                            <option value="<?= htmlspecialchars($key) ?>"
                                <?= $key === $currentSize ? 'selected' : '' ?>>
                                <?= htmlspecialchars($spec['label']) ?> — <?= htmlspecialchars($spec['hint']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">
                        El grande es el diseño original. Mediano y pequeño son maquetas propias.
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-4 mt-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="show_cover" name="show_cover"
                        <?= $checked('show_cover') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="show_cover">Incluir portada</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="show_tone_tag" name="show_tone_tag"
                        <?= $checked('show_tone_tag') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="show_tone_tag">Etiquetas de tono</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="use_qr" name="use_qr"
                        <?= $checked('use_qr') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="use_qr">
                        QR en los cupones
                        <span class="text-secondary small d-block">
                            Sustituye al sello «vale ya». Permite canjear escaneando el cupón impreso.
                        </span>
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="is_draft" name="is_draft"
                        <?= $checked('is_draft') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_draft">
                        Borrador
                        <span class="text-secondary small d-block">Mientras esté marcado, solo la ves tú.</span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h6 text-uppercase text-secondary mb-3">Portada</h2>

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="cover_eyebrow">Cejilla</label>
                    <input type="text" class="form-control" id="cover_eyebrow" name="cover_eyebrow"
                           maxlength="120" value="<?= htmlspecialchars($value('cover_eyebrow')) ?>"
                           placeholder="<?= htmlspecialchars($hint('cover_eyebrow')) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="cover_title">Titular</label>
                    <input type="text" class="form-control" id="cover_title" name="cover_title"
                           maxlength="120" value="<?= htmlspecialchars($value('cover_title')) ?>"
                           placeholder="<?= htmlspecialchars($hint('cover_title')) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="cover_accent">Segunda línea</label>
                    <input type="text" class="form-control" id="cover_accent" name="cover_accent"
                           maxlength="120" value="<?= htmlspecialchars($value('cover_accent')) ?>"
                           placeholder="<?= htmlspecialchars($hint('cover_accent')) ?>">
                    <div class="form-text">Va en el color de acento.</div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="cover_note">Nota</label>
                    <textarea class="form-control" id="cover_note" name="cover_note" rows="3"
                              maxlength="600"
                              placeholder="<?= htmlspecialchars($hint('cover_note')) ?>"><?= htmlspecialchars($value('cover_note')) ?></textarea>
                    <?php /* Los textos son opcionales: lo que quede vacío no
                             sale en la portada. Los ejemplos son solo
                             placeholder, no se guardan solos. */ ?>
                    <div class="form-text">
                        Todos opcionales: lo que dejes vacío no aparece en la portada. Los textos en gris son
                        solo ejemplos.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-accent btn-lg">
        <?= $isEdit ? 'Guardar cambios' : 'Crear cuponera' ?>
    </button>
</form>

<?php if ($isEdit): ?>
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <h2 class="h4 mb-0 me-auto">Cupones <span class="text-secondary fs-6">(<?= count($coupons) ?>)</span></h2>
        <?php /* Opcional y a petición: la cuponera nace vacía. */ ?>
        <form method="post" action="<?= BASE_URL ?>/cuponeras/<?= (int) $book['id'] ?>/cupones/ejemplos">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-sm btn-outline-secondary">
                Cargar los 20 cupones de ejemplo
            </button>
        </form>
    </div>

    <?php if (!$coupons): ?>
        <div class="empty-state mb-4">
            <p class="mb-1">Esta cuponera todavía no tiene cupones.</p>
            <p class="mb-0 text-secondary">
                Escribe el primero abajo, o carga los 20 del diseño para partir de ellos y editarlos.
            </p>
        </div>
    <?php endif; ?>

    <div class="vstack gap-3 mb-4">
        <?php foreach ($coupons as $coupon): ?>
            <form method="post"
                  action="<?= BASE_URL ?>/cuponeras/<?= (int) $book['id'] ?>/cupones/<?= (int) $coupon['id'] ?>/editar"
                  class="card coupon-row">
                <?= Csrf::field() ?>
                <div class="card-body">
                    <div class="row g-2 align-items-end">
                        <div class="col-auto">
                            <span class="coupon-row__number"><?= (int) $coupon['position'] ?></span>
                        </div>
                        <div class="col-12 col-lg-3">
                            <label class="form-label small">Título</label>
                            <input type="text" class="form-control form-control-sm" name="title"
                                   maxlength="120" value="<?= htmlspecialchars($coupon['title']) ?>" required>
                        </div>
                        <div class="col-12 col-lg-4">
                            <label class="form-label small">Descripción</label>
                            <input type="text" class="form-control form-control-sm" name="description"
                                   maxlength="400" value="<?= htmlspecialchars($coupon['description']) ?>">
                        </div>
                        <div class="col-6 col-lg-1">
                            <label class="form-label small">Tono</label>
                            <input type="text" class="form-control form-control-sm" name="tone"
                                   maxlength="40" value="<?= htmlspecialchars($coupon['tone']) ?>">
                        </div>
                        <div class="col-6 col-lg-1">
                            <label class="form-label small">Duración</label>
                            <input type="text" class="form-control form-control-sm" name="duration"
                                   maxlength="40" value="<?= htmlspecialchars($coupon['duration']) ?>">
                        </div>
                        <div class="col-12 col-lg-auto d-flex gap-2">
                            <button type="submit" class="btn btn-sm btn-outline-light">Guardar</button>
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                    data-bs-toggle="modal" data-bs-target="#confirmModal"
                                    data-title="Borrar cupón"
                                    data-body="Se borrará «<?= htmlspecialchars($coupon['title']) ?>» y su historial. No se puede deshacer."
                                    data-url="<?= BASE_URL ?>/cuponeras/<?= (int) $book['id'] ?>/cupones/<?= (int) $coupon['id'] ?>/borrar"
                                    data-variant="danger">
                                Borrar
                            </button>
                        </div>
                    </div>
                    <div class="mt-2 small text-secondary">
                        <code><?= htmlspecialchars($coupon['code']) ?></code>
                        · <?= htmlspecialchars(StatusMachine::label($coupon['current_status'])) ?>
                    </div>
                </div>
            </form>
        <?php endforeach; ?>
    </div>

    <form method="post" action="<?= BASE_URL ?>/cuponeras/<?= (int) $book['id'] ?>/cupones/nuevo"
          class="card">
        <?= Csrf::field() ?>
        <div class="card-body">
            <h3 class="h6 text-uppercase text-secondary mb-3">Añadir cupón</h3>
            <div class="row g-2 align-items-end">
                <div class="col-12 col-lg-3">
                    <label class="form-label small">Título</label>
                    <input type="text" class="form-control form-control-sm" name="title"
                           maxlength="120" required>
                </div>
                <div class="col-12 col-lg-4">
                    <label class="form-label small">Descripción</label>
                    <input type="text" class="form-control form-control-sm" name="description" maxlength="400">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label small">Tono</label>
                    <input type="text" class="form-control form-control-sm" name="tone" maxlength="40">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label small">Duración</label>
                    <input type="text" class="form-control form-control-sm" name="duration" maxlength="40">
                </div>
                <div class="col-12 col-lg-auto">
                    <button type="submit" class="btn btn-sm btn-accent">Añadir</button>
                </div>
            </div>
        </div>
    </form>
<?php endif; ?>
