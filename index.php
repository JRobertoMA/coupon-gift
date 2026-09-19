<?php

declare(strict_types=1);

define('BASE_PATH', __DIR__);

require_once BASE_PATH . '/config/app.php';

// ── Errores ─────────────────────────────────────────────────────────────────
// Sin esto una excepción cualquiera es un 500 en blanco sin rastro. Las
// deprecaciones se silencian: las lanza vendor/ bajo PHP 8.4 (endroid) y si
// display_errors está activo en el hosting ensucian la salida antes de las
// cabeceras. Se registra en logs/app.log siempre; se muestra solo con APP_DEBUG.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/logs/app.log');

set_exception_handler(function (Throwable $e): void {
    error_log(sprintf('[coupon-gift] %s: %s en %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
    error_log($e->getTraceAsString());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    if (APP_DEBUG) {
        echo '<pre style="white-space:pre-wrap;font:14px/1.5 monospace;padding:24px">'
            . htmlspecialchars(get_class($e) . ': ' . $e->getMessage()) . "\n"
            . htmlspecialchars(str_replace(BASE_PATH, '', $e->getFile()) . ':' . $e->getLine()) . "\n\n"
            . htmlspecialchars(str_replace(BASE_PATH, '', $e->getTraceAsString()))
            . '</pre>';
    } else {
        echo '<!DOCTYPE html><meta charset="utf-8"><title>Error</title>'
            . '<p style="font:16px sans-serif;padding:40px">Algo ha fallado. El detalle está en <code>logs/app.log</code>.</p>';
    }
    exit;
});

// Los errores fatales no pasan por el manejador de excepciones.
register_shutdown_function(function (): void {
    $err = error_get_last();
    if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log(sprintf('[coupon-gift] FATAL %s en %s:%d', $err['message'], $err['file'], $err['line']));
        if (APP_DEBUG && !headers_sent()) {
            echo '<pre style="white-space:pre-wrap;font:14px/1.5 monospace;padding:24px">FATAL: '
                . htmlspecialchars($err['message'] . ' en ' . str_replace(BASE_PATH, '', $err['file']) . ':' . $err['line'])
                . '</pre>';
        }
    }
});

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
