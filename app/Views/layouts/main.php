<?php
/**
 * Layout principal.
 *
 * @var string     $content
 * @var string     $title
 * @var array|null $_authUser
 */
$flash = $_SESSION['message'] ?? null;
unset($_SESSION['message']);
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= htmlspecialchars(Csrf::token()) ?>">
<title><?= htmlspecialchars($title ?? 'Cuponeras') ?> · Cuponeras</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="<?= htmlspecialchars(Design::FONTS_HREF) ?>" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/app.css">
</head>
<body>

<nav class="navbar navbar-expand-lg border-bottom border-secondary-subtle mb-4">
    <div class="container">
        <a class="navbar-brand brand-hand" href="<?= BASE_URL ?>/cuponeras">Cuponeras</a>
        <?php if ($_authUser): ?>
            <div class="ms-auto d-flex align-items-center gap-3">
                <span class="text-secondary small d-none d-sm-inline">
                    <?= htmlspecialchars($_authUser['name']) ?>
                </span>
                <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/ajustes">Ajustes</a>
                <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/salir">Salir</a>
            </div>
        <?php endif; ?>
    </div>
</nav>

<main class="container pb-5">
    <?= $content ?>
</main>

<?php /* Los mensajes flash se pintan como Toast auto-descartable, no como
         alerta estática. */ ?>
<?php if ($flash): ?>
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div class="toast align-items-center text-bg-<?= htmlspecialchars($flash['type']) ?> border-0"
         role="alert" data-bs-delay="5000">
        <div class="d-flex">
            <div class="toast-body"><?= htmlspecialchars($flash['text']) ?></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                    data-bs-dismiss="toast" aria-label="Cerrar"></button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php /* Un único modal compartido para las confirmaciones, poblado desde los
         data-* del botón que lo abre. Nunca confirm(). */ ?>
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">¿Seguro?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="post" id="confirmForm" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                    <button type="submit" class="btn btn-danger" id="confirmSubmit">Confirmar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/public/js/app.js"></script>
</body>
</html>
