<?php

declare(strict_types=1);

/**
 * Ajustes de la cuenta: vincular Telegram y ver la cola de avisos.
 *
 * Existe porque en hosting compartido no se puede ejecutar cli/user.php, y
 * porque cuando un aviso no llega hay que poder ver el porqué sin entrar en la
 * base de datos.
 *
 * POST /ajustes action=tg_code     genera un código CG-XXXXXXXX para /vincular (10 min; anula el anterior)
 * POST /ajustes action=tg_cancel   anula el código pendiente
 * POST /ajustes action=tg_test     mensaje de prueba del bot a tu Telegram
 * POST /ajustes/telegram/desvincular   desvincula Telegram (solo recibirás correo); va por el #confirmModal, que
 *                                      solo envía el CSRF a una URL, por eso es una ruta aparte
 */
class SettingsController extends Controller
{
    public function show(): void
    {
        Auth::require();
        $userId = (int) Auth::user();

        $user = (new User())->find($userId);
        if ($user === null) {
            Auth::logout();
            $this->redirect('/entrar');
        }

        // El código se ve mientras no caduque ni se haya usado (al vincular, telegram_chat_id deja de estar vacío)
        $code = $_SESSION['tg_code'] ?? null;
        if (!empty($user['telegram_chat_id']) || !is_array($code) || (int) $code['expires_at'] < time()) {
            unset($_SESSION['tg_code']);
            $code = null;
        }
        $botName = null;
        if ($code !== null) {
            if (!isset($_SESSION['tg_bot'])) {
                try {
                    $_SESSION['tg_bot'] = (string) (BotClient::me()['username'] ?? '');
                } catch (RuntimeException $e) {
                    $_SESSION['tg_bot'] = '';
                }
            }
            $botName = $_SESSION['tg_bot'] ?: null;
        }

        $this->render('ajustes/index', [
            'title'         => 'Ajustes',
            'user'          => $user,
            'notifications' => (new Notification())->recentForUser($userId),
            'tgCode'        => $code,
            'botName'       => $botName,
            // Solo booleanos: nunca se enseña un secreto.
            'mailReady'     => MailChannel::configured(),
            'telegramReady' => TelegramChannel::configured(),
            'cronSecret'    => (string) env('CRON_SECRET', '') !== '',
        ]);
    }

    public function update(): void
    {
        Auth::require();
        Csrf::require();
        $userId = (int) Auth::user();
        $users  = new User();

        switch ((string) ($_POST['action'] ?? '')) {
            case 'tg_code':
                if (!TelegramChannel::configured()) {
                    $this->flash('Telegram no está configurado en el servidor (BOT_BACKEND_URL y BOT_PROJECT_KEY).', 'danger');
                    break;
                }
                $_SESSION['tg_code'] = (new TelegramLink())->createCode($userId);
                break;

            case 'tg_cancel':
                (new TelegramLink())->discardCode($userId);
                unset($_SESSION['tg_code']);
                break;

            case 'tg_test':
                $chatId = (string) ($users->find($userId)['telegram_chat_id'] ?? '');
                if ($chatId === '') {
                    $this->flash('Primero vincula tu Telegram.', 'danger');
                    break;
                }
                try {
                    BotClient::test((int) $chatId);
                    $this->flash('Mensaje de prueba enviado. Revisa Telegram.');
                } catch (RuntimeException $e) {
                    $this->flash('No se pudo enviar: ' . $e->getMessage(), 'danger');
                }
                break;

            default:
                $this->flash('Acción no válida.', 'danger');
        }
        $this->redirect('/ajustes');
    }

    public function unlinkTelegram(): void
    {
        Auth::require();
        Csrf::require();
        $userId = (int) Auth::user();

        (new User())->setTelegramChatId($userId, null);
        (new TelegramLink())->discardCode($userId);
        unset($_SESSION['tg_code']);
        $this->flash('Telegram desvinculado: los avisos te llegarán solo por correo.');
        $this->redirect('/ajustes');
    }
}
