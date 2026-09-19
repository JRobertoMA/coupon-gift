<?php

declare(strict_types=1);

/**
 * Envío por Telegram.
 *
 * Usa cURL directo en vez de Guzzle: son dos llamadas muy simples y así el
 * worker no depende de que composer install haya corrido.
 */
class TelegramChannel
{
    private const API = 'https://api.telegram.org/bot';

    public static function configured(): bool
    {
        return (string) env('TELEGRAM_BOT_TOKEN', '') !== '';
    }

    /** Envía el aviso que corresponda al payload encolado. */
    public static function send(array $payload): void
    {
        $chatId = (string) ($payload['to_chat_id'] ?? '');
        if ($chatId === '') {
            throw new RuntimeException('El destinatario no tiene telegram_chat_id.');
        }

        if (($payload['kind'] ?? '') === 'requested') {
            self::sendRequested($chatId, $payload);
            return;
        }
        self::sendResolved($chatId, $payload);
    }

    /**
     * Aviso de canje, con los botones de confirmar o rechazar.
     *
     * callback_data lleva la acción y el id del cupón; Telegram limita ese
     * campo a 64 bytes, de sobra para «fulfill:12345».
     */
    private static function sendRequested(string $chatId, array $payload): void
    {
        $text = "🎟️ <b>" . self::esc($payload['from_name']) . "</b> ha pedido un cupón\n\n"
              . "<b>" . self::esc($payload['coupon_title']) . "</b>\n"
              . "<i>" . self::esc($payload['book_title']) . "</i>\n"
              . "Código: <code>" . self::esc($payload['coupon_code']) . "</code>";

        self::call('sendMessage', [
            'chat_id'      => $chatId,
            'text'         => $text,
            'parse_mode'   => 'HTML',
            'reply_markup' => json_encode([
                'inline_keyboard' => [[
                    ['text' => '✅ Cumplido',  'callback_data' => 'fulfill:' . $payload['coupon_id']],
                    ['text' => '✖️ Rechazar', 'callback_data' => 'decline:' . $payload['coupon_id']],
                ]],
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }

    private static function sendResolved(string $chatId, array $payload): void
    {
        $icon = ($payload['status'] ?? '') === 'fulfilled' ? '✅' : '✖️';
        $text = $icon . " <b>" . self::esc($payload['from_name']) . "</b> marcó tu cupón como "
              . "<b>" . self::esc($payload['status_label']) . "</b>\n\n"
              . "<b>" . self::esc($payload['coupon_title']) . "</b>\n"
              . APP_URL . '/c/' . $payload['book_slug'];

        self::call('sendMessage', [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ]);
    }

    /** Responde al toque de un botón para que deje de girar la ruedita. */
    public static function answerCallback(string $callbackId, string $text): void
    {
        self::call('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text'              => $text,
        ]);
    }

    /** Reescribe el mensaje para que los botones no se puedan volver a pulsar. */
    public static function editMessage(string $chatId, int $messageId, string $text): void
    {
        self::call('editMessageText', [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ]);
    }

    /** Llamada a la API. Lanza excepción si no responde ok. */
    private static function call(string $method, array $params): array
    {
        $token = (string) env('TELEGRAM_BOT_TOKEN', '');
        if ($token === '') {
            throw new RuntimeException('Falta TELEGRAM_BOT_TOKEN en .env.');
        }

        $ch = curl_init(self::API . $token . '/' . $method);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $raw  = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Telegram no respondió: ' . $err);
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded) || ($decoded['ok'] ?? false) !== true) {
            throw new RuntimeException('Telegram devolvió error: ' . mb_substr((string) $raw, 0, 300));
        }
        return $decoded;
    }

    private static function esc(mixed $text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
