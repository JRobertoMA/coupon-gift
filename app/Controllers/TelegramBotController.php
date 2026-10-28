<?php

declare(strict_types=1);

/**
 * POST /telegram/bot — eventos que reenvía el backend compartido del bot (proyecto telegram-bot).
 *
 * El webhook de Telegram es del bot, no de coupon-gift: el bot recibe los updates y reenvía aquí, firmado con
 * HMAC (BotSignature, BOT_PROJECT_KEY), lo que es de este proyecto: los botones con prefijo «cg» (y los antiguos
 * «fulfill» / «decline») y los códigos /vincular CG-…. No hay sesión: la firma es la autorización.
 *
 * Responde con el sobre {data, error, status}; en data va lo que debe hacer el bot:
 *   {"toast": "…", "alert": bool}           aviso al pulsar un botón
 *   {"edit": true, "text": "…", "buttons": []}  reescribir el mensaje del botón (y quitarle los botones)
 *   {"text": "…"}                           responder con un mensaje nuevo
 *   null                                    no hacer nada
 */
class TelegramBotController extends Controller
{
    public function handle(): void
    {
        $body = file_get_contents('php://input') ?: '';
        $ok   = hash_equals(BotClient::project(), (string) ($_SERVER['HTTP_X_BOT_PROJECT'] ?? ''))
            && BotSignature::verify(
                (string) env('BOT_PROJECT_KEY', ''),
                (string) ($_SERVER['HTTP_X_BOT_TIMESTAMP'] ?? ''),
                (string) ($_SERVER['HTTP_X_BOT_SIGNATURE'] ?? ''),
                $body
            );
        if (!$ok) {
            $this->jsonError('Firma no válida.', 401);
        }

        $event = json_decode($body, true);
        if (!is_array($event) || !is_string($event['type'] ?? null)) {
            $this->jsonError('Evento no válido.', 422);
        }

        $this->json(match ($event['type']) {
            'ping'     => ['pong' => true],
            'link'     => $this->link($event),
            'callback' => $this->callback($event),
            default    => null,
        });
    }

    /** /vincular CG-XXXXXXXX: guarda quién es esa cuenta de Telegram. */
    private function link(array $event): array
    {
        $fromId = (int) ($event['from_id'] ?? 0);
        if ($fromId <= 0) {
            return ['text' => 'No se pudo identificar tu cuenta de Telegram.'];
        }
        try {
            $user = (new TelegramLink())->redeem((string) ($event['code'] ?? ''), $fromId);
        } catch (DomainException $e) {
            return ['text' => '✖ ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
                . "\nGenera un código nuevo en la web: Ajustes → Vincular Telegram."];
        }
        return ['text' => '✅ Cuenta vinculada: <b>' . htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') . "</b>.\n"
            . 'A partir de ahora los avisos de las cuponeras te llegan aquí.'];
    }

    /** Un botón del aviso de canje: «✅ Cumplido» o «✖️ Rechazar». */
    private function callback(array $event): array
    {
        // «cg:fulfill:12»; los avisos enviados antes de pasar por el bot llevan «fulfill:12» sin prefijo
        if (!preg_match('/^(?:cg:)?(fulfill|decline):(\d{1,10})$/', (string) ($event['data'] ?? ''), $m)) {
            return ['toast' => 'Acción no reconocida.'];
        }
        [, $action, $couponId] = $m;

        // Quién pulsa: la persona (from.id), no el chat del mensaje. Así, aunque el aviso estuviera en un grupo,
        // solo actúa quien tiene esa cuenta de Telegram vinculada.
        $user = (new User())->findByTelegramChatId((string) (int) ($event['from_id'] ?? 0));
        if ($user === null) {
            return ['toast' => 'No te reconozco. Vincula tu cuenta en Ajustes → Vincular Telegram.', 'alert' => true];
        }

        $coupon = (new Coupon())->findFull((int) $couponId);
        if ($coupon === null) {
            return ['toast' => 'Ese cupón ya no existe.', 'alert' => true];
        }

        $role = StatusMachine::roleOf($coupon, (int) $user['id']);
        $to   = $action === 'fulfill' ? StatusMachine::FULFILLED : StatusMachine::DECLINED;
        try {
            StatusMachine::apply($coupon, $to, (int) $user['id'], $role, 'telegram');
        } catch (DomainException $e) {
            return ['toast' => $e->getMessage(), 'alert' => true];
        }

        Notifier::couponResolved($coupon, $to);
        Notifier::kickWorker();

        $label = StatusMachine::label($to);
        // Se reescribe el mensaje sin los botones para que no se pulsen dos veces.
        return [
            'toast'   => 'Marcado como ' . $label . '.',
            'edit'    => true,
            'text'    => sprintf(
                "%s <b>%s</b>\n\n%s\nCódigo: <code>%s</code>",
                $to === StatusMachine::FULFILLED ? '✅' : '✖️',
                htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $coupon['title'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $coupon['code'], ENT_QUOTES, 'UTF-8')
            ),
            'buttons' => [],
        ];
    }
}
