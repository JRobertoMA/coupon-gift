<?php

declare(strict_types=1);

/**
 * Worker de la bandeja de salida: envía las notificaciones encoladas.
 *
 * El envío NO va dentro de la petición HTTP: con mpm_prefork y 768 MB para todo
 * el host, un SMTP lento bloquea un worker de Apache durante segundos.
 *
 * Se lanza solo tras cada canje (Notifier::kickWorker) y conviene además un
 * cron de red de seguridad cada 5 minutos:
 *
 *   docker exec -u www-data php-apache php /var/www/html/coupon-gift/cli/worker.php
 *
 * OJO: siempre con -u www-data. Sin eso corre como root y los ficheros que cree
 * (el .lock, los logs) quedan ilegibles para Apache.
 */

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/config/app.php';

if (PHP_SAPI !== 'cli') {
    // Por HTTP exige el secreto: si no, cualquiera podría dispararlo. Se
    // comprueba DESPUÉS de cargar config/app.php, que es quien lee el .env;
    // con getenv() antes de eso el secreto siempre estaba vacío y daba 403.
    $secret = (string) env('CRON_SECRET', '');
    $given  = $_GET['token'] ?? $_SERVER['HTTP_X_CRON_TOKEN'] ?? '';
    if ($secret === '' || !hash_equals($secret, (string) $given)) {
        http_response_code(403);
        exit('No autorizado.');
    }
}
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/design.php';

if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

require_once BASE_PATH . '/app/Core/Model.php';

spl_autoload_register(function (string $class): void {
    foreach (['/app/Models/', '/app/Services/', '/app/Core/'] as $dir) {
        $file = BASE_PATH . $dir . $class . '.php';
        if (is_file($file)) { require_once $file; return; }
    }
});

/** Escribe una línea con marca de tiempo. */
function wlog(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    if (PHP_SAPI === 'cli') {
        echo $line;
    }
    @file_put_contents(BASE_PATH . '/logs/worker.log', $line, FILE_APPEND);
}

// ── Un solo worker a la vez ─────────────────────────────────────────────────
// flock NO bloqueante: si ya hay uno corriendo, este se va sin hacer nada en
// lugar de acumular procesos esperando.
$lockPath = BASE_PATH . '/data/worker.lock';
$lock = @fopen($lockPath, 'c');

if ($lock === false) {
    wlog('ERROR: no se pudo abrir ' . $lockPath . ' (¿permisos de www-data?)');
    exit(1);
}

if (!flock($lock, LOCK_EX | LOCK_NB)) {
    // Ya hay otro worker: salida limpia, no es un error.
    exit(0);
}

$notifications = new Notification();
$pending       = $notifications->pending();

if (!$pending) {
    flock($lock, LOCK_UN);
    fclose($lock);
    exit(0);
}

$sent = 0;
$failed = 0;

foreach ($pending as $row) {
    $payload = json_decode((string) $row['payload'], true);
    if (!is_array($payload)) {
        $notifications->markFailed((int) $row['id'], 'Payload ilegible.');
        $failed++;
        continue;
    }

    // El coupon_id de la fila manda sobre el del payload: es la fuente fiable.
    $payload['coupon_id'] = (int) $row['coupon_id'];

    try {
        if ($row['channel'] === 'telegram') {
            TelegramChannel::send($payload);
        } else {
            MailChannel::send($payload);
        }
        $notifications->markSent((int) $row['id']);
        $sent++;
    } catch (Throwable $e) {
        $notifications->markFailed((int) $row['id'], $e->getMessage());
        $failed++;
        wlog(sprintf('FALLO #%d (%s): %s', $row['id'], $row['channel'], $e->getMessage()));
    }
}

wlog(sprintf('Enviadas %d, fallidas %d, de %d en cola.', $sent, $failed, count($pending)));

flock($lock, LOCK_UN);
fclose($lock);
