<?php

declare(strict_types=1);

/**
 * Carga el .env y define la configuración de la aplicación.
 *
 * Se carga antes que nada: config/database.php da por hecho que env() existe.
 */

$__env = [];
$__envFile = __DIR__ . '/../.env';
if (is_readable($__envFile)) {
    foreach (file($__envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        // Los comentarios del .env van con ';' (igual que el resto de proyectos),
        // pero se acepta '#' porque es lo habitual en otros ficheros.
        if ($line === '' || $line[0] === ';' || $line[0] === '#') continue;
        if (!str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $__env[trim($key)] = trim($val, " \t\"'");
    }
}

/** Lee una variable del .env con valor por defecto. */
function env(string $key, ?string $default = null): ?string
{
    global $__env;
    $val = $__env[$key] ?? $_ENV[$key] ?? getenv($key) ?: null;
    return ($val === null || $val === '') ? $default : (string) $val;
}

date_default_timezone_set('America/Mexico_City');

// Con APP_DEBUG=1 los errores se muestran en pantalla. Solo para diagnosticar;
// en producción va a 0 y el error real queda en logs/app.log.
define('APP_DEBUG', env('APP_DEBUG', '0') === '1');

// Ruta pública de la app dentro del docroot. En local es /coupon-gift (docroot
// compartido con más proyectos); en un hosting donde la app cuelga de la raíz
// del dominio se pone BASE_URL=/ en el .env, que aquí se convierte en ''.
define('BASE_URL', rtrim(env('BASE_URL', '/coupon-gift'), '/'));

// URL absoluta: la que se mete en los QR y en los correos. Tiene que ser
// alcanzable desde el móvil, por eso vive en .env y no se deduce de $_SERVER.
define('APP_URL', rtrim(env('APP_URL', 'http://localhost:81' . BASE_URL), '/'));

// Los cupones por hoja dependen del tamaño elegido: ver Design::SIZES.
