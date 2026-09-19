<?php
/**
 * Layout de la vista de impresión.
 *
 * Deliberadamente sin Bootstrap ni nada del resto de la app: en el papel solo
 * puede acabar el diseño. La barra de herramientas lleva .no-print.
 *
 * @var string $content
 * @var string $title
 * @var string $themeStyle
 * @var array  $book
 * @var array  $sizeSpec   Design::SIZES[$size]
 */
$landscape = ($sizeSpec['orientation'] ?? 'portrait') === 'landscape';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title) ?> · Imprimir</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="<?= htmlspecialchars(Design::FONTS_HREF) ?>" rel="stylesheet">

<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/sheet.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/document.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/controls.css">

<?php /* @page no se puede condicionar por una clase del body, así que la
         orientación se emite aquí según el tamaño elegido. margin: 0 porque el
         diseño va a sangre (ver sheet.css). */ ?>
<style>@page { size: letter <?= $landscape ? 'landscape' : 'portrait' ?>; margin: 0; }</style>
</head>
<body class="doc<?= $landscape ? ' sheet--landscape' : '' ?>">

<?php require BASE_PATH . '/app/Views/cuponeras/_controles.php'; ?>

<!-- Todo el tema son variables CSS sobre este único div, igual que en el
     prototipo: el panel de controles solo reescribe estas variables. -->
<div id="theme-root" style="<?= htmlspecialchars($themeStyle) ?>">
    <div class="sheets">
        <?= $content ?>
    </div>
</div>

<script>
    window.DESIGN_TABLES = <?= json_encode(Design::clientTables(), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= BASE_URL ?>/public/js/controls.js"></script>
</body>
</html>
