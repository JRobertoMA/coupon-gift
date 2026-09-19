<?php

declare(strict_types=1);

/**
 * Webhook de Telegram.
 *
 * No hay sesión aquí: la autorización es la cabecera secreta que Telegram
 * devuelve en cada llamada. Requiere que APP_URL sea HTTPS público.
 *
 * Registrar el webhook una vez con:
 *   php cli/telegram_webhook.php set
 */
class TelegramWebhookController extends Controller
{
    public function handle(): void
    {
        $expected = (string) env('TELEGRAM_WEBHOOK_SECRET', '');
        $given    = (string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');

        // Sin secreto configurado se rechaza todo: es preferible a dejar el
        // endpoint abierto a cualquiera que sepa la URL.
        if ($expected === '' || !hash_equals($expected, $given)) {
            $this->jsonError('No autorizado.', 403);
        }

        $update = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($update)) {
            $this->json(['ok' => true]);
        }

        if (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);
        }

        // Cualquier otra cosa (un /start, un mensaje suelto) se ignora sin ruido.
        $this->json(['ok' => true]);
    }

    /** Un botón pulsado: «✅ Cumplido» o «✖️ Rechazar». */
    private function handleCallback(array $callback): void
    {
        $callbackId = (string) ($callback['id'] ?? '');
        $chatId     = (string) ($callback['message']['chat']['id'] ?? '');
        $messageId  = (int) ($callback['message']['message_id'] ?? 0);
        $data       = (string) ($callback['data'] ?? '');

        [$action, $couponId] = array_pad(explode(':', $data, 2), 2, '');

        if (!in_array($action, ['fulfill', 'decline'], true) || !ctype_digit((string) $couponId)) {
            $this->answer($callbackId, 'Acción no reconocida.');
        }

        // Quien pulsa tiene que ser el dueño del cupón, no basta con que el
        // mensaje le llegara: se comprueba contra el chat_id guardado.
        $user = (new User())->findByTelegramChatId($chatId);
        if ($user === null) {
            $this->answer($callbackId, 'No te reconozco. Vincula tu cuenta primero.');
        }

        $coupon = (new Coupon())->findFull((int) $couponId);
        if ($coupon === null) {
            $this->answer($callbackId, 'Ese cupón ya no existe.');
        }

        $role = StatusMachine::roleOf($coupon, (int) $user['id']);
        $to   = $action === 'fulfill' ? StatusMachine::FULFILLED : StatusMachine::DECLINED;

        try {
            StatusMachine::apply($coupon, $to, (int) $user['id'], $role, 'telegram');
        } catch (DomainException $e) {
            $this->answer($callbackId, $e->getMessage());
        }

        Notifier::couponResolved($coupon, $to);
        Notifier::kickWorker();

        $label = StatusMachine::label($to);
        TelegramChannel::answerCallback($callbackId, 'Marcado como ' . $label . '.');

        // Se reescribe el mensaje sin los botones para que no se pulsen dos veces.
        if ($messageId > 0) {
            $icon = $to === StatusMachine::FULFILLED ? '✅' : '✖️';
            TelegramChannel::editMessage($chatId, $messageId, sprintf(
                "%s <b>%s</b>\n\n%s\nCódigo: <code>%s</code>",
                $icon,
                htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $coupon['title'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $coupon['code'], ENT_QUOTES, 'UTF-8')
            ));
        }

        $this->json(['ok' => true]);
    }

    /** Contesta al botón y termina: Telegram siempre debe recibir un 200. */
    private function answer(string $callbackId, string $text): never
    {
        if ($callbackId !== '') {
            try {
                TelegramChannel::answerCallback($callbackId, $text);
            } catch (Throwable $e) {
                error_log('[coupon-gift] answerCallbackQuery falló: ' . $e->getMessage());
            }
        }
        $this->json(['ok' => true]);
    }
}
