<?php
/**
 * @var array $user
 * @var array $notifications
 * @var bool  $mailReady
 * @var bool  $telegramReady
 * @var bool  $cronSecret
 * @var array|null  $tgCode   código pendiente ['code', 'expires_at'] o null
 * @var string|null $botName  @usuario del bot (solo si hay código pendiente)
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

                <?php if (!empty($user['telegram_chat_id'])): ?>
                    <p class="mb-3"><span class="badge text-bg-success">Vinculado</span> Los avisos te llegan por Telegram.</p>
                    <div class="d-flex flex-wrap gap-2">
                        <form method="post" action="<?= BASE_URL ?>/ajustes">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="tg_test">
                            <button type="submit" class="btn btn-outline-secondary btn-sm">Enviar prueba</button>
                        </form>
                        <button type="button" class="btn btn-outline-danger btn-sm"
                                data-bs-toggle="modal" data-bs-target="#confirmModal"
                                data-url="<?= BASE_URL ?>/ajustes/telegram/desvincular"
                                data-title="Desvincular"
                                data-body="Dejarás de recibir los avisos por Telegram (seguirán llegando por correo) y los botones de los avisos ya no actuarán en tu nombre.">
                            Desvincular
                        </button>
                    </div>

                <?php elseif (!$telegramReady): ?>
                    <p class="mb-0 text-secondary">Telegram no está configurado en el servidor: faltan <code>BOT_BACKEND_URL</code> y <code>BOT_PROJECT_KEY</code> en el <code>.env</code>.</p>

                <?php elseif ($tgCode !== null): ?>
                    <?php $command = '/vincular ' . $tgCode['code']; ?>
                    <p class="mb-2">
                        Envía este mensaje al bot<?php if ($botName): ?>
                            <a href="https://t.me/<?= htmlspecialchars($botName) ?>" target="_blank" rel="noopener">@<?= htmlspecialchars($botName) ?></a><?php endif; ?>
                        desde tu Telegram:
                    </p>
                    <div class="input-group mb-2">
                        <input type="text" class="form-control font-monospace" value="<?= htmlspecialchars($command) ?>" readonly aria-label="Mensaje para el bot">
                        <button type="button" class="btn btn-outline-secondary" data-copy-button="<?= htmlspecialchars($command) ?>">Copiar</button>
                    </div>
                    <div class="form-text mb-3">
                        Vale una sola vez, hasta las <?= htmlspecialchars(date('H:i', (int) $tgCode['expires_at'])) ?>.
                        Cuando el bot te confirme, recarga esta página.
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?= BASE_URL ?>/ajustes" class="btn btn-accent btn-sm">Ya lo envié</a>
                        <form method="post" action="<?= BASE_URL ?>/ajustes">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="tg_cancel">
                            <button type="submit" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                        </form>
                    </div>

                <?php else: ?>
                    <p>Vincula tu cuenta para recibir los avisos de las cuponeras por Telegram y confirmar los canjes con un botón.</p>
                    <form method="post" action="<?= BASE_URL ?>/ajustes">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="tg_code">
                        <button type="submit" class="btn btn-accent">Vincular Telegram</button>
                    </form>
                <?php endif; ?>
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
                        <tr><td>Telegram (backend del bot)</td><td class="text-end"><?= $badge($telegramReady) ?></td></tr>
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
