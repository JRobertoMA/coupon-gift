<?php

declare(strict_types=1);

/**
 * Enlaces de un solo uso para confirmar desde el correo.
 *
 * Un correo no puede llevar botones que hagan POST, así que el mensaje lleva
 * dos enlaces: uno con action=fulfill y otro con action=decline.
 */
class ActionToken extends Model
{
    protected string $table = 'action_tokens';

    /** Días que vive un enlace antes de caducar. */
    private const TTL_DAYS = 30;

    /** Crea el par de enlaces (cumplir / rechazar) de un canje. */
    public function issuePair(int $couponId): array
    {
        $out = [];
        foreach (['fulfill', 'decline'] as $action) {
            $token = bin2hex(random_bytes(32));
            $this->execute(
                'INSERT INTO action_tokens (coupon_id, token, action, expires_at)
                 VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))',
                [$couponId, $token, $action, self::TTL_DAYS]
            );
            $out[$action] = $token;
        }
        return $out;
    }

    /** El token si existe, no ha caducado y no se ha usado; si no, null. */
    public function findUsable(string $token): ?array
    {
        return $this->queryOne(
            'SELECT * FROM action_tokens
              WHERE token = ? AND used_at IS NULL AND expires_at > NOW()',
            [$token]
        );
    }

    public function markUsed(int $id): void
    {
        $this->execute('UPDATE action_tokens SET used_at = NOW() WHERE id = ?', [$id]);
    }

    /**
     * Invalida los enlaces pendientes de un cupón.
     *
     * Se llama al resolver por cualquier vía: si se confirma desde Telegram,
     * los enlaces del correo dejan de servir en el acto.
     */
    public function invalidateFor(int $couponId): void
    {
        $this->execute(
            'UPDATE action_tokens SET used_at = NOW() WHERE coupon_id = ? AND used_at IS NULL',
            [$couponId]
        );
    }
}
