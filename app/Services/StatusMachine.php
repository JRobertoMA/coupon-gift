<?php

declare(strict_types=1);

/**
 * Transiciones de estado de un cupón.
 *
 * Es la única puerta para cambiar el estado: valida contra la whitelist, exige
 * que quien actúa tenga el papel correcto, escribe el historial e invalida los
 * enlaces del correo que hayan quedado colgando.
 */
class StatusMachine
{
    public const PENDING   = 'pending';
    public const REQUESTED = 'requested';
    public const FULFILLED = 'fulfilled';
    public const DECLINED  = 'declined';
    public const CANCELLED = 'cancelled';

    /** Papel que debe tener quien ejecuta la transición. */
    private const RECIPIENT = 'recipient';  // quien recibe el regalo
    private const OWNER     = 'owner';      // quien lo cumple

    /**
     * Whitelist: desde => [hacia => papel exigido].
     * Cualquier combinación que no esté aquí se rechaza.
     */
    private const TRANSITIONS = [
        self::PENDING   => [self::REQUESTED => self::RECIPIENT],
        self::REQUESTED => [
            self::FULFILLED => self::OWNER,
            self::DECLINED  => self::OWNER,
            self::CANCELLED => self::RECIPIENT,
        ],
        self::DECLINED  => [self::REQUESTED => self::RECIPIENT],
        self::CANCELLED => [self::REQUESTED => self::RECIPIENT],
        self::FULFILLED => [],  // cumplido es definitivo
    ];

    /** Etiquetas que ve el usuario. */
    public const LABELS = [
        self::PENDING   => 'Sin usar',
        self::REQUESTED => 'Pedido',
        self::FULFILLED => 'Cumplido',
        self::DECLINED  => 'Rechazado',
        self::CANCELLED => 'Cancelado',
    ];

    /** Color de Bootstrap por estado, para badges. */
    public const VARIANTS = [
        self::PENDING   => 'secondary',
        self::REQUESTED => 'warning',
        self::FULFILLED => 'success',
        self::DECLINED  => 'danger',
        self::CANCELLED => 'dark',
    ];

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? $status;
    }

    public static function variant(string $status): string
    {
        return self::VARIANTS[$status] ?? 'secondary';
    }

    /** ¿El cupón se muestra tachado como usado? Solo cuando está cumplido. */
    public static function isSpent(string $status): bool
    {
        return $status === self::FULFILLED;
    }

    /** Papel del usuario en esta cuponera, o null si no es ninguno de los dos. */
    public static function roleOf(array $coupon, int $userId): ?string
    {
        if ((int) $coupon['recipient_id'] === $userId) return self::RECIPIENT;
        if ((int) $coupon['owner_id'] === $userId)     return self::OWNER;
        return null;
    }

    /**
     * ¿Puede este usuario llevar el cupón a ese estado?
     *
     * $role se pasa directamente cuando la acción no viene de una sesión web
     * (Telegram, o un enlace firmado del correo).
     */
    public static function can(string $from, string $to, ?string $role): bool
    {
        $required = self::TRANSITIONS[$from][$to] ?? null;
        return $required !== null && $required === $role;
    }

    /** Las transiciones que puede hacer ahora mismo quien tiene ese papel. */
    public static function availableFor(string $from, ?string $role): array
    {
        $out = [];
        foreach (self::TRANSITIONS[$from] ?? [] as $to => $required) {
            if ($required === $role) $out[] = $to;
        }
        return $out;
    }

    /**
     * Aplica la transición.
     *
     * @throws DomainException si la transición no está permitida para ese papel.
     * @return string el estado nuevo
     */
    public static function apply(array $coupon, string $to, ?int $actorId, ?string $role, string $channel, string $note = ''): string
    {
        $from = $coupon['current_status'] ?? self::PENDING;

        if (!self::can($from, $to, $role)) {
            throw new DomainException(sprintf(
                'No se puede pasar de «%s» a «%s».',
                self::label($from),
                self::label($to)
            ));
        }

        (new CouponStatus())->add((int) $coupon['id'], $to, $actorId, $channel, $note);

        // Al resolverse el canje, los enlaces del correo dejan de valer aunque
        // la confirmación haya llegado por Telegram o por la web.
        if (in_array($to, [self::FULFILLED, self::DECLINED, self::CANCELLED], true)) {
            (new ActionToken())->invalidateFor((int) $coupon['id']);
        }

        return $to;
    }
}
