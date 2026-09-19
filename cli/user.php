<?php

declare(strict_types=1);

/**
 * Alta y mantenimiento de cuentas.
 *
 * No hay registro abierto por la web: la app es para dos personas, así que las
 * cuentas se crean desde aquí.
 *
 *   docker exec -it -u www-data php-apache php /var/www/html/coupon-gift/cli/user.php list
 *   docker exec -it -u www-data php-apache php /var/www/html/coupon-gift/cli/user.php add "Nombre" correo@ejemplo.com
 *   docker exec -it -u www-data php-apache php /var/www/html/coupon-gift/cli/user.php password correo@ejemplo.com
 *   docker exec -it -u www-data php-apache php /var/www/html/coupon-gift/cli/user.php rename correo@ejemplo.com "Nombre nuevo"
 *   docker exec -it -u www-data php-apache php /var/www/html/coupon-gift/cli/user.php email correo@actual.com correo@nuevo.com
 *   docker exec -it -u www-data php-apache php /var/www/html/coupon-gift/cli/user.php telegram correo@ejemplo.com 123456789
 *
 * La contraseña se pide por consola y no se pasa como argumento: los argumentos
 * quedan en el historial del shell y en `ps`.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo por línea de órdenes.');
}

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/app/Core/Model.php';
require_once BASE_PATH . '/app/Models/User.php';

$command = $argv[1] ?? 'help';
$users   = new User();

/** Pide una contraseña sin mostrarla al teclear. */
function promptPassword(string $label): string
{
    echo $label . ': ';
    if (function_exists('shell_exec') && stripos(PHP_OS_FAMILY, 'win') === false) {
        @shell_exec('stty -echo 2>/dev/null');
    }
    $value = trim((string) fgets(STDIN));
    @shell_exec('stty echo 2>/dev/null');
    echo PHP_EOL;
    return $value;
}

switch ($command) {
    case 'list':
        $rows = $users->all('name ASC');
        if (!$rows) {
            echo "No hay ninguna cuenta todavía.\n";
            break;
        }
        printf("%-4s %-22s %-32s %s\n", 'ID', 'Nombre', 'Correo', 'Telegram');
        foreach ($rows as $row) {
            printf("%-4d %-22s %-32s %s\n",
                $row['id'], $row['name'], $row['email'], $row['telegram_chat_id'] ?: '—');
        }
        break;

    case 'add':
        $name  = $argv[2] ?? '';
        $email = $argv[3] ?? '';

        if ($name === '' || $email === '') {
            fwrite(STDERR, "Uso: user.php add \"Nombre\" correo@ejemplo.com\n");
            exit(1);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            fwrite(STDERR, "Ese correo no es válido.\n");
            exit(1);
        }
        if ($users->findByEmail($email) !== null) {
            fwrite(STDERR, "Ya existe una cuenta con ese correo.\n");
            exit(1);
        }

        $password = promptPassword('Contraseña');
        if (mb_strlen($password) < 8) {
            fwrite(STDERR, "La contraseña necesita al menos 8 caracteres.\n");
            exit(1);
        }
        if ($password !== promptPassword('Repite la contraseña')) {
            fwrite(STDERR, "No coinciden.\n");
            exit(1);
        }

        $id = $users->create($name, $email, $password);
        echo "Cuenta creada con id $id.\n";
        break;

    case 'password':
        $email = $argv[2] ?? '';
        $user  = $email !== '' ? $users->findByEmail($email) : null;

        if ($user === null) {
            fwrite(STDERR, "No hay ninguna cuenta con ese correo.\n");
            exit(1);
        }

        $password = promptPassword('Contraseña nueva');
        if (mb_strlen($password) < 8) {
            fwrite(STDERR, "La contraseña necesita al menos 8 caracteres.\n");
            exit(1);
        }
        if ($password !== promptPassword('Repite la contraseña')) {
            fwrite(STDERR, "No coinciden.\n");
            exit(1);
        }

        $users->execute('UPDATE users SET password_hash = ? WHERE id = ?', [
            password_hash($password, PASSWORD_DEFAULT), $user['id'],
        ]);
        echo "Contraseña actualizada.\n";
        break;

    case 'rename':
        $email = $argv[2] ?? '';
        $name  = trim($argv[3] ?? '');
        $user  = $email !== '' ? $users->findByEmail($email) : null;

        if ($user === null) {
            fwrite(STDERR, "No hay ninguna cuenta con ese correo.\n");
            exit(1);
        }
        if ($name === '') {
            fwrite(STDERR, "Uso: user.php rename correo@ejemplo.com \"Nombre nuevo\"\n");
            exit(1);
        }

        $users->execute('UPDATE users SET name = ? WHERE id = ?', [mb_substr($name, 0, 80), $user['id']]);
        echo "Nombre cambiado a «{$name}».\n";
        break;

    case 'email':
        $email    = $argv[2] ?? '';
        $newEmail = mb_strtolower(trim($argv[3] ?? ''));
        $user     = $email !== '' ? $users->findByEmail($email) : null;

        if ($user === null) {
            fwrite(STDERR, "No hay ninguna cuenta con ese correo.\n");
            exit(1);
        }
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            fwrite(STDERR, "Uso: user.php email correo@actual.com correo@nuevo.com\n");
            exit(1);
        }
        if ($users->findByEmail($newEmail) !== null) {
            fwrite(STDERR, "Ya existe otra cuenta con ese correo.\n");
            exit(1);
        }

        $users->execute('UPDATE users SET email = ? WHERE id = ?', [$newEmail, $user['id']]);
        echo "Correo cambiado a $newEmail. A partir de ahora entra con ese.\n";
        break;

    case 'telegram':
        $email  = $argv[2] ?? '';
        $chatId = $argv[3] ?? '';
        $user   = $email !== '' ? $users->findByEmail($email) : null;

        if ($user === null) {
            fwrite(STDERR, "No hay ninguna cuenta con ese correo.\n");
            exit(1);
        }

        $users->setTelegramChatId((int) $user['id'], $chatId !== '' ? $chatId : null);
        echo $chatId !== ''
            ? "Telegram vinculado a $chatId.\n"
            : "Telegram desvinculado.\n";
        break;

    default:
        echo <<<TXT
        Uso:
          user.php list
          user.php add "Nombre" correo@ejemplo.com
          user.php password correo@ejemplo.com
          user.php rename correo@ejemplo.com "Nombre nuevo"
          user.php email correo@actual.com correo@nuevo.com
          user.php telegram correo@ejemplo.com <chat_id>

        Para averiguar tu chat_id: escribe al bot y abre
        https://api.telegram.org/bot<TOKEN>/getUpdates

        TXT;
        break;
}
