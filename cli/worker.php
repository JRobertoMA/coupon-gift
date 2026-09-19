<?php

declare(strict_types=1);

/**
 * Worker de la bandeja de salida, por línea de órdenes.
 *
 * La lógica está en app/Services/OutboxWorker.php; esto solo la invoca. En
 * hosting compartido, donde no hay CLI utilizable y cli/ está bloqueado por el
 * .htaccess, se usa la ruta /cron/worker?token=CRON_SECRET en su lugar.
 *
 *   docker exec -u www-data php-apache php /var/www/html/coupon-gift/cli/worker.php
 *
 * OJO: siempre con -u www-data. Como root, el .lock y el log quedan ilegibles
 * para Apache.
 */

// Solo desde consola. No basta con mirar PHP_SAPI: bajo FastCGI, PHP_BINARY es
// php-cgi y al lanzarlo desde consola (Notifier::kickWorker) el SAPI es 'cgi',
// pero no hay petición HTTP. Lo que delata una petición web es REQUEST_METHOD.
if (PHP_SAPI !== 'cli' && !empty($_SERVER['REQUEST_METHOD'])) {
    http_response_code(403);
    exit("Solo por línea de órdenes. Por HTTP usa /cron/worker?token=…\n");
}

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/config/app.php';
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

$result = OutboxWorker::run();

if ($result['status'] === 'locked') {
    exit(0);  // ya hay otro worker: salida limpia
}

printf("Enviadas %d, fallidas %d, de %d en cola.\n", $result['sent'], $result['failed'], $result['pending']);
foreach ($result['errors'] as $err) {
    echo "  FALLO $err\n";
}

exit($result['status'] === 'ok' ? 0 : 1);
