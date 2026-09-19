<?php

declare(strict_types=1);

/**
 * Procesa la bandeja de salida: envía lo encolado en `notifications`.
 *
 * Lo usan dos puertas: cli/worker.php (línea de órdenes o cron del sistema) y
 * la ruta /cron/worker?token=… (cron por URL, lo único disponible en hosting
 * compartido donde el `php` de CLI es otra versión y cli/ está bloqueado).
 *
 * Un solo worker a la vez: flock NO bloqueante en data/worker.lock. Si ya hay
 * uno corriendo, el segundo devuelve 'locked' y se va sin hacer nada.
 */
class OutboxWorker
{
    /** Reintentos por notificación antes de darla por fallida. */
    public const MAX_ATTEMPTS = Notification::MAX_ATTEMPTS;

    /**
     * @return array{status:string, sent:int, failed:int, pending:int, errors:array<int,string>}
     */
    public static function run(int $limit = 25): array
    {
        $result = ['status' => 'ok', 'sent' => 0, 'failed' => 0, 'pending' => 0, 'errors' => []];

        $lockPath = BASE_PATH . '/data/worker.lock';
        $lock = @fopen($lockPath, 'c');
        if ($lock === false) {
            $result['status'] = 'error';
            $result['errors'][] = 'No se pudo abrir ' . $lockPath . ' (¿permisos?).';
            return $result;
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            $result['status'] = 'locked';
            return $result;
        }

        try {
            $notifications = new Notification();
            $pending       = $notifications->pending($limit);
            $result['pending'] = count($pending);

            foreach ($pending as $row) {
                $payload = json_decode((string) $row['payload'], true);
                if (!is_array($payload)) {
                    $notifications->markFailed((int) $row['id'], 'Payload ilegible.');
                    $result['failed']++;
                    continue;
                }

                // El coupon_id de la fila manda sobre el del payload.
                $payload['coupon_id'] = (int) $row['coupon_id'];

                try {
                    if ($row['channel'] === 'telegram') {
                        TelegramChannel::send($payload);
                    } else {
                        MailChannel::send($payload);
                    }
                    $notifications->markSent((int) $row['id']);
                    $result['sent']++;
                } catch (Throwable $e) {
                    $notifications->markFailed((int) $row['id'], $e->getMessage());
                    $result['failed']++;
                    $result['errors'][] = sprintf('#%d (%s): %s', $row['id'], $row['channel'], $e->getMessage());
                }
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        self::log($result);
        return $result;
    }

    private static function log(array $result): void
    {
        if ($result['pending'] === 0 && !$result['errors']) return;

        $line = sprintf('[%s] Enviadas %d, fallidas %d, de %d en cola.',
            date('Y-m-d H:i:s'), $result['sent'], $result['failed'], $result['pending']);
        foreach ($result['errors'] as $err) {
            $line .= PHP_EOL . '    FALLO ' . $err;
        }
        @file_put_contents(BASE_PATH . '/logs/worker.log', $line . PHP_EOL, FILE_APPEND);
    }
}
