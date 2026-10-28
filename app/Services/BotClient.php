<?php

declare(strict_types=1);

/**
 * Cliente del backend compartido del bot de Telegram (proyecto telegram-bot, en IONOS).
 *
 * El bot es quien tiene el token y el webhook; coupon-gift solo tiene su URL (BOT_BACKEND_URL) y su clave
 * (BOT_PROJECT_KEY), con la que firma cada petición (BotSignature). Los botones que pulsa el usuario llegan por el
 * otro lado: el bot los reenvía firmados a POST /telegram/bot (TelegramBotController).
 *
 * cURL directo, como el resto de canales: el worker no depende de composer install.
 * Lanza RuntimeException si algo falla, que es lo que espera OutboxWorker para reintentar. Los mensajes nunca
 * contienen la clave.
 */
class BotClient
{
    public const DEFAULT_PROJECT = 'coupons';
    private const TIMEOUT = 15;

    public static function configured(): bool
    {
        return self::backendUrl() !== '' && (string) env('BOT_PROJECT_KEY', '') !== '';
    }

    public static function backendUrl(): string
    {
        return rtrim((string) env('BOT_BACKEND_URL', ''), '/');
    }

    /** Nombre del proyecto en el bot (cli/project.php add <nombre> en el bot). */
    public static function project(): string
    {
        return (string) env('BOT_PROJECT', self::DEFAULT_PROJECT);
    }

    /**
     * Envía un mensaje HTML. $buttons: filas de {text, data|url|copy}; $ref queda en el bot asociado al mensaje.
     *
     * @return array {"message_id": …, "queued": bool}
     */
    public static function send(int $chatId, string $html, ?array $buttons = null, ?string $ref = null): array
    {
        return self::request('POST', 'api/send.php', array_filter([
            'chat'       => $chatId,
            'text'       => $html,
            'parse_mode' => 'HTML',
            'buttons'    => $buttons,
            'ref'        => $ref,
        ], fn ($v) => $v !== null));
    }

    /** @return array {"id": …, "username": …, "name": …} */
    public static function me(): array
    {
        return self::request('GET', 'api/me.php');
    }

    /** Mensaje de prueba del bot en nombre de este proyecto. */
    public static function test(int $chatId): array
    {
        return self::request('POST', 'api/test.php', ['chat' => $chatId]);
    }

    /** @return array el «data» del sobre {data, error, status} del bot */
    private static function request(string $method, string $path, ?array $payload = null): array
    {
        if (!self::configured()) {
            throw new RuntimeException('Faltan BOT_BACKEND_URL y BOT_PROJECT_KEY en .env.');
        }
        $body    = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Accept: application/json'];
        foreach (BotSignature::headers(self::project(), (string) env('BOT_PROJECT_KEY', ''), $body) as $k => $v) {
            $headers[] = $k . ': ' . $v;
        }
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => $headers,
        ];
        if ($method === 'POST') {
            $options[CURLOPT_POST]        = true;
            $options[CURLOPT_POSTFIELDS]  = $body;
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }

        $ch = curl_init(self::backendUrl() . '/' . $path);
        curl_setopt_array($ch, $options);
        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('El bot no respondió (' . self::backendUrl() . '): ' . $err);
        }
        $json = json_decode((string) $raw, true);
        if ($status === 200 && is_array($json) && array_key_exists('data', $json)) {
            return is_array($json['data']) ? $json['data'] : [];
        }
        $detail = is_array($json) && !empty($json['error']) ? (string) $json['error'] : 'HTTP ' . $status;
        throw new RuntimeException(match ($status) {
            401     => 'El bot rechazó la firma: revisa BOT_PROJECT_KEY y BOT_PROJECT en .env.',
            301, 302, 307, 308 => 'BOT_BACKEND_URL redirige a otra dirección: usa la URL final (con https://).',
            404     => 'BOT_BACKEND_URL no apunta al bot: ' . self::backendUrl() . '/' . $path . ' no existe (HTTP 404).',
            default => 'Bot: ' . $detail,
        });
    }
}
