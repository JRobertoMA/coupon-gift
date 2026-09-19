<?php
/**
 * Layout mínimo y centrado, sin navegación.
 *
 * Lo usan el acceso y las páginas a las que se llega desde un enlace del
 * correo, donde no hay sesión y no habría nada que enseñar en una barra.
 *
 * @var string $content
 * @var string $title
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
<body class="auth-body">

<main class="auth-shell">
    <?= $content ?>
</main>

<?php if ($flash): ?>
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div class="toast align-items-center text-bg-<?= htmlspecialchars($flash['type']) ?> border-0"
         role="alert" data-bs-delay="6000">
        <div class="d-flex">
            <div class="toast-body"><?= htmlspecialchars($flash['text']) ?></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                    data-bs-dismiss="toast" aria-label="Cerrar"></button>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/public/js/app.js"></script>
</body>
</html>
