<?php

declare(strict_types=1);

define('BASE_PATH', __DIR__);

require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/design.php';

// Dependencias de Composer (PHPMailer, endroid/qr-code, Guzzle). La app arranca
// sin ellas: QrService cae a un hueco y el worker avisa de lo que falta.
if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

require_once BASE_PATH . '/app/Core/Router.php';
require_once BASE_PATH . '/app/Core/Controller.php';
require_once BASE_PATH . '/app/Core/Model.php';
require_once BASE_PATH . '/app/Core/Auth.php';
require_once BASE_PATH . '/app/Core/Csrf.php';

// Una clase por fichero, sin namespace.
spl_autoload_register(function (string $class): void {
    foreach (['/app/Controllers/', '/app/Models/', '/app/Services/', '/app/Core/'] as $dir) {
        $file = BASE_PATH . $dir . $class . '.php';
        if (is_file($file)) { require_once $file; return; }
    }
});

// Sesión: 8 horas de inactividad, cookie de 30 días. `secure` se activa solo
// cuando la petición llega por HTTPS, para que siga funcionando en localhost.
$isHttps = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

ini_set('session.gc_maxlifetime', '28800');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 2592000,
    'path'     => BASE_URL . '/',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$router = new Router();
require BASE_PATH . '/routes/web.php';
$router->dispatch();
