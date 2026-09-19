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

    /** Últimos avisos que afectan al usuario (los que recibe y los de sus cuponeras). */
    public function recentForUser(int $userId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        return $this->query(
            "SELECT n.id, n.channel, n.status, n.attempts, n.last_error, n.sent_at, n.created_at,
                    c.title AS coupon_title, c.code AS coupon_code, u.name AS to_name
               FROM notifications n
               JOIN coupons c      ON c.id = n.coupon_id
               JOIN coupon_books b ON b.id = c.book_id
               JOIN users u        ON u.id = n.to_user_id
              WHERE n.to_user_id = ? OR b.owner_id = ? OR b.recipient_id = ?
              ORDER BY n.id DESC
              LIMIT $limit",
            [$userId, $userId, $userId]
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
