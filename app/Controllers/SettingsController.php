<?php

declare(strict_types=1);

/**
 * Ajustes de la cuenta: vincular Telegram y ver la cola de avisos.
 *
 * Existe porque en hosting compartido no se puede ejecutar cli/user.php, y
 * porque cuando un aviso no llega hay que poder ver el porqué sin entrar en la
 * base de datos.
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

        $this->render('ajustes/index', [
            'title'         => 'Ajustes',
            'user'          => $user,
            'notifications' => (new Notification())->recentForUser($userId),
            // Solo booleanos: nunca se enseña un secreto.
            'mailReady'     => MailChannel::configured(),
            'telegramReady' => TelegramChannel::configured(),
            'webhookSecret' => (string) env('TELEGRAM_WEBHOOK_SECRET', '') !== '',
            'cronSecret'    => (string) env('CRON_SECRET', '') !== '',
        ]);
    }

    public function update(): void
    {
        Auth::require();
        Csrf::require();
        $userId = (int) Auth::user();

        $chatId = trim((string) ($_POST['telegram_chat_id'] ?? ''));

        // Un chat_id de Telegram es un entero (negativo si es un grupo).
        if ($chatId !== '' && !preg_match('/^-?\d{1,20}$/', $chatId)) {
            $this->flash('El chat ID de Telegram tiene que ser un número.', 'danger');
            $this->redirect('/ajustes');
        }

        (new User())->setTelegramChatId($userId, $chatId !== '' ? $chatId : null);

        $this->flash($chatId !== '' ? 'Telegram vinculado.' : 'Telegram desvinculado.');
        $this->redirect('/ajustes');
    }
}
