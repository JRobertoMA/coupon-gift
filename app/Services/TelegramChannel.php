<?php

declare(strict_types=1);

/**
 * Envío por Telegram, a través del backend compartido del bot (BotClient).
 *
 * coupon-gift ya no tiene el token del bot: lo tiene el bot, que también es quien recibe los botones y los reenvía
 * a TelegramBotController. Lo llama OutboxWorker; cualquier fallo lanza RuntimeException y el aviso se reintenta.
 */
class TelegramChannel
{
    public static function configured(): bool
    {
        return BotClient::configured();
    }

    /** Envía el aviso que corresponda al payload encolado. */
    public static function send(array $payload): void
    {
        $chatId = (string) ($payload['to_chat_id'] ?? '');
        if (!preg_match('/^-?\d{1,20}$/', $chatId)) {
            throw new RuntimeException('El destinatario no tiene Telegram vinculado.');
        }

        if (($payload['kind'] ?? '') === 'requested') {
            self::sendRequested((int) $chatId, $payload);
            return;
        }
        self::sendResolved((int) $chatId, $payload);
    }

    /**
     * Aviso de canje, con los botones de confirmar o rechazar.
     *
     * callback_data «cg:<acción>:<id>»: el prefijo «cg» es el que el bot enruta a coupon-gift (los avisos enviados
     * antes, con «fulfill:<id>» / «decline:<id>» sin prefijo, también se siguen atendiendo). Telegram limita ese
     * campo a 64 bytes, de sobra.
     */
    private static function sendRequested(int $chatId, array $payload): void
    {
        $text = "🎟️ <b>" . self::esc($payload['from_name']) . "</b> ha pedido un cupón\n\n"
              . "<b>" . self::esc($payload['coupon_title']) . "</b>\n"
              . "<i>" . self::esc($payload['book_title']) . "</i>\n"
              . "Código: <code>" . self::esc($payload['coupon_code']) . "</code>";

        BotClient::send($chatId, $text, [[
            ['text' => '✅ Cumplido',  'data' => 'cg:fulfill:' . $payload['coupon_id']],
            ['text' => '✖️ Rechazar', 'data' => 'cg:decline:' . $payload['coupon_id']],
        ]], 'coupon:' . $payload['coupon_id']);
    }

    private static function sendResolved(int $chatId, array $payload): void
    {
        $icon = ($payload['status'] ?? '') === 'fulfilled' ? '✅' : '✖️';
        $text = $icon . " <b>" . self::esc($payload['from_name']) . "</b> marcó tu cupón como "
              . "<b>" . self::esc($payload['status_label']) . "</b>\n\n"
              . "<b>" . self::esc($payload['coupon_title']) . "</b>\n"
              . APP_URL . '/c/' . $payload['book_slug'];

        BotClient::send($chatId, $text, null, 'coupon:' . $payload['coupon_id']);
    }

    private static function esc(mixed $text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
