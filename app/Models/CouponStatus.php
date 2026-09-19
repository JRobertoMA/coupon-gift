<?php

declare(strict_types=1);

/**
 * Historial de estados. Solo INSERT: nunca se actualiza ni se borra una fila,
 * así queda el rastro completo de quién cambió qué y por qué canal.
 */
class CouponStatus extends Model
{
    protected string $table = 'coupon_status';

    public function add(int $couponId, string $status, ?int $actorId, string $channel, string $note = ''): int
    {
        return $this->execute(
            'INSERT INTO coupon_status (coupon_id, status, actor_id, channel, note)
             VALUES (?,?,?,?,?)',
            [$couponId, $status, $actorId, $channel, mb_substr($note, 0, 255)]
        );
    }

    public function current(int $couponId): string
    {
        $row = $this->queryOne(
            'SELECT status FROM coupon_status WHERE coupon_id = ? ORDER BY id DESC LIMIT 1',
            [$couponId]
        );
        return $row['status'] ?? 'pending';
    }

    /** Historial completo de un cupón, del más reciente al más antiguo. */
    public function history(int $couponId): array
    {
        return $this->query(
            'SELECT s.*, u.name AS actor_name
               FROM coupon_status s
               LEFT JOIN users u ON u.id = s.actor_id
              WHERE s.coupon_id = ?
              ORDER BY s.id DESC',
            [$couponId]
        );
    }
}
