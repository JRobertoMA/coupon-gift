<?php

declare(strict_types=1);

/**
 * Conexión PDO a MariaDB.
 *
 * DB_HOST es el hostname `mariadb`, no la IP LAN: php-apache y mariadb
 * comparten la red Docker dev_net (verificado: resuelve a 172.30.30.5). La IP
 * LAN del host cambia y dejaría la app con 500.
 */

define('DB_HOST',    env('DB_HOST', 'mariadb'));
define('DB_PORT',    env('DB_PORT', '3306'));
define('DB_NAME',    env('DB_NAME', 'coupon_gift'));
define('DB_USER',    env('DB_USER', 'root'));
define('DB_PASS',    env('DB_PASS', ''));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

/**
 * Devuelve la conexión PDO compartida.
 *
 * @param bool $withoutDb true para conectar sin seleccionar base de datos
 *                        (solo para crearla en las migraciones).
 */
function getDB(bool $withoutDb = false): PDO
{
    static $pdo = null, $pdoRaw = null;

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    if ($withoutDb) {
        if ($pdoRaw === null) {
            $dsn = sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
            $pdoRaw = new PDO($dsn, DB_USER, DB_PASS, $options);
        }
        return $pdoRaw;
    }

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}
