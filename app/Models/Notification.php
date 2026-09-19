<?php

declare(strict_types=1);

/**
 * Bandeja de salida.
 *
 * El canje encola aquí y devuelve al instante; cli/worker.php es quien envía.
 * Con mpm_prefork y 768 MB para todo el host, un SMTP lento dentro de la
 * petición HTTP bloquea un worker de Apache durante segundos.
 */
class Notification extends Model
{
    protected string $table = 'notifications';

    /** Reintentos antes de dar una notificación por fallida. */
    public const MAX_ATTEMPTS = 3;

    public function enqueue(int $couponId, string $channel, int $toUserId, array $payload): int
    {
        return $this->execute(
            'INSERT INTO notifications (coupon_id, channel, to_user_id, payload) VALUES (?,?,?,?)',
            [$couponId, $channel, $toUserId, json_encode($payload, JSON_UNESCAPED_UNICODE)]
        );
    }

    /** Lo que queda por enviar, más antiguo primero. */
    public function pending(int $limit = 25): array
    {
        $limit = max(1, min(100, $limit));
        return $this->query(
            "SELECT * FROM notifications
              WHERE status = 'queued' AND attempts < ?
              ORDER BY id ASC
              LIMIT $limit",
            [self::MAX_ATTEMPTS]
        );
    }

    public function markSent(int $id): void
    {
        $this->execute(
            "UPDATE notifications SET status = 'sent', sent_at = NOW(), attempts = attempts + 1,
                    last_error = '' WHERE id = ?",
            [$id]
        );
    }

    /** Suma un intento y guarda el error; al llegar al tope la marca fallida. */
    public function markFailed(int $id, string $error): void
    {
        $this->execute(
            "UPDATE notifications
                SET attempts   = attempts + 1,
                    last_error = ?,
                    status     = IF(attempts + 1 >= ?, 'failed', 'queued')
              WHERE id = ?",
            [mb_substr($error, 0, 500), self::MAX_ATTEMPTS, $id]
        );
    }
}
