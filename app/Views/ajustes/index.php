<?php
/**
 * @var array $user
 * @var array $notifications
 * @var bool  $mailReady
 * @var bool  $telegramReady
 * @var bool  $webhookSecret
 * @var bool  $cronSecret
 */
$badge = fn (bool $ok, string $yes = 'Configurado', string $no = 'Falta en .env'): string =>
    '<span class="badge text-bg-' . ($ok ? 'success' : 'danger') . '">' . ($ok ? $yes : $no) . '</span>';
?>
<h1 class="brand-hand mb-4">Ajustes</h1>

<div class="row g-4">
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-secondary mb-3">Tu Telegram</h2>

                <form method="post" action="<?= BASE_URL ?>/ajustes">
                    <?= Csrf::field() ?>
                    <label class="form-label" for="telegram_chat_id">Chat ID</label>
                    <div class="input-group mb-2">
                        <input type="text" class="form-control" id="telegram_chat_id" name="telegram_chat_id"
                               inputmode="numeric" pattern="-?[0-9]{1,20}"
                               value="<?= htmlspecialchars((string) ($user['telegram_chat_id'] ?? '')) ?>"
                               placeholder="123456789">
                        <button type="submit" class="btn btn-accent">Guardar</button>
                    </div>
                    <div class="form-text">
                        Para averiguarlo: escribe cualquier cosa al bot en Telegram y abre en el navegador
                        <code>https://api.telegram.org/bot&lt;TOKEN&gt;/getUpdates</code>.
                        El número está en <code>message.chat.id</code>. Déjalo vacío para desvincular.
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-secondary mb-3">Estado del servidor</h2>
                <?php /* Solo booleanos: aquí nunca se enseña un secreto. */ ?>
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><td>Correo (SMTP)</td><td class="text-end"><?= $badge($mailReady) ?></td></tr>
                        <tr><td>Token del bot de Telegram</td><td class="text-end"><?= $badge($telegramReady) ?></td></tr>
                        <tr><td>Secreto del webhook</td><td class="text-end"><?= $badge($webhookSecret) ?></td></tr>
                        <tr><td>Secreto del cron</td><td class="text-end"><?= $badge($cronSecret) ?></td></tr>
                    </tbody>
                </table>
                <p class="form-text mt-3 mb-0">
                    Los avisos los envía el worker. En hosting sin cron del sistema, programa una llamada cada
                    5 minutos a <code><?= htmlspecialchars(APP_URL) ?>/cron/worker?token=&lt;CRON_SECRET&gt;</code>.
                </p>
            </div>
        </div>
    </div>
</div>

<h2 class="h5 mt-5 mb-3">Últimos avisos</h2>

<?php if (!$notifications): ?>
    <div class="empty-state">
        <p class="mb-0 text-secondary">Todavía no se ha encolado ningún aviso. Aparecen al canjear o confirmar un cupón.</p>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th>Cuándo</th>
                    <th>Canal</th>
                    <th>Para</th>
                    <th>Cupón</th>
                    <th>Estado</th>
                    <th>Detalle</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notifications as $n): ?>
                    <tr>
                        <td class="text-nowrap small"><?= htmlspecialchars(date('d/m H:i', strtotime($n['created_at']))) ?></td>
                        <td><?= $n['channel'] === 'telegram' ? 'Telegram' : 'Correo' ?></td>
                        <td><?= htmlspecialchars($n['to_name']) ?></td>
                        <td>
                            <?= htmlspecialchars($n['coupon_title']) ?>
                            <code class="small text-secondary"><?= htmlspecialchars($n['coupon_code']) ?></code>
                        </td>
                        <td>
                            <?php $v = ['sent' => 'success', 'failed' => 'danger', 'queued' => 'warning'][$n['status']] ?? 'secondary'; ?>
                            <span class="badge text-bg-<?= $v ?>">
                                <?= ['sent' => 'Enviado', 'failed' => 'Fallido', 'queued' => 'En cola'][$n['status']] ?? $n['status'] ?>
                            </span>
                            <?php if ((int) $n['attempts'] > 0 && $n['status'] !== 'sent'): ?>
                                <span class="small text-secondary"><?= (int) $n['attempts'] ?>/<?= Notification::MAX_ATTEMPTS ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-secondary" style="max-width: 360px">
                            <?php if ($n['status'] === 'sent'): ?>
                                <?= htmlspecialchars(date('d/m H:i', strtotime($n['sent_at']))) ?>
                            <?php elseif ($n['last_error'] !== ''): ?>
                                <?= htmlspecialchars($n['last_error']) ?>
                            <?php else: ?>
                                Esperando al worker.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
