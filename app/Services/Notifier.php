<?php

declare(strict_types=1);

/**
 * Encola notificaciones. NO envía: eso es cosa de cli/worker.php.
 *
 * Se encola un mensaje por canal disponible del destinatario. Telegram solo si
 * esa persona tiene telegram_chat_id; el correo siempre que tenga email.
 */
class Notifier
{
    /** Alguien ha pedido un cupón: avisa a quien tiene que cumplirlo. */
    public static function couponRequested(array $coupon): void
    {
        $tokens = (new ActionToken())->issuePair((int) $coupon['id']);

        self::queueFor($coupon, (int) $coupon['owner_id'], [
            'kind'          => 'requested',
            'coupon_id'     => (int) $coupon['id'],
            'coupon_code'   => $coupon['code'],
            'coupon_title'  => $coupon['title'],
            'book_title'    => $coupon['book_title'],
            'from_name'     => $coupon['recipient_name'],
            'to_name'       => $coupon['owner_name'],
            'to_email'      => $coupon['owner_email'],
            'to_chat_id'    => $coupon['owner_chat_id'],
            'fulfill_token' => $tokens['fulfill'],
            'decline_token' => $tokens['decline'],
        ]);
    }

    /** El cupón se ha resuelto: avisa a quien lo pidió de cómo quedó. */
    public static function couponResolved(array $coupon, string $status): void
    {
        self::queueFor($coupon, (int) $coupon['recipient_id'], [
            'kind'         => 'resolved',
            'coupon_id'    => (int) $coupon['id'],
            'status'       => $status,
            'status_label' => StatusMachine::label($status),
            'coupon_code'  => $coupon['code'],
            'coupon_title' => $coupon['title'],
            'book_title'   => $coupon['book_title'],
            'book_slug'    => $coupon['slug'],
            'from_name'    => $coupon['owner_name'],
            'to_name'      => $coupon['recipient_name'],
            'to_email'     => $coupon['recipient_email'],
            'to_chat_id'   => $coupon['recipient_chat_id'],
        ]);
    }

    /** Encola el mismo aviso por todos los canales que tenga esa persona. */
    private static function queueFor(array $coupon, int $toUserId, array $payload): void
    {
        $notifications = new Notification();
        $couponId      = (int) $coupon['id'];

        if (!empty($payload['to_chat_id'])) {
            $notifications->enqueue($couponId, 'telegram', $toUserId, $payload);
        }
        if (!empty($payload['to_email'])) {
            $notifications->enqueue($couponId, 'email', $toUserId, $payload);
        }
    }

    /**
     * Lanza el worker en segundo plano para que el aviso salga ya.
     *
     * setsid + nohup para que sobreviva al final de la petición. Si falla no
     * pasa nada: el cron recoge la cola en la siguiente pasada.
     */
    public static function kickWorker(): void
    {
        // En hosting compartido exec() suele estar en disable_functions:
        // function_exists() sigue devolviendo true pero llamarla avisa. En
        // ese caso se deja la cola para el cron.
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (!function_exists('exec') || in_array('exec', $disabled, true)) return;

        // Se usa el MISMO binario que sirve la web (PHP_BINARY), no `php` a
        // secas: en IONOS `php` es una versión antigua que ni parsea el código.
        // Bajo FastCGI PHP_BINARY es php-cgi, que también ejecuta ficheros.
        // Es solo un empujón: si no arranca, el cron recoge la cola igual.
        $php    = PHP_BINARY !== '' ? escapeshellarg(PHP_BINARY) : 'php';
        $script = escapeshellarg(BASE_PATH . '/cli/worker.php');
        @exec('setsid nohup ' . $php . ' ' . $script . ' > /dev/null 2>&1 &');
    }
}
