<?php

declare(strict_types=1);

class Auth
{
    /** Intentos fallidos permitidos antes de bloquear. */
    private const MAX_ATTEMPTS = 5;

    /** Ventana del bloqueo, en minutos. */
    private const WINDOW_MINUTES = 15;

    public static function user(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function userData(): ?array
    {
        return $_SESSION['user_data'] ?? null;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    /** Exige sesión; si no la hay, manda a /entrar recordando a dónde iba. */
    public static function require(): void
    {
        if (!self::check()) {
            $_SESSION['intended'] = Router::currentUri();
            header('Location: ' . BASE_URL . '/entrar');
            exit;
        }
    }

    public static function login(array $user): void
    {
        // Obligatorio al iniciar sesión: evita la fijación de sesión.
        session_regenerate_id(true);
        $_SESSION['user_id']   = (int) $user['id'];
        $_SESSION['user_data'] = [
            'id'    => (int) $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
        ];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    // ── Límite de intentos de acceso ──────────────────────────────────────────

    public static function clientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    /** ¿Está bloqueado este email o esta IP por intentos fallidos? */
    public static function isLocked(string $email): bool
    {
        $stmt = getDB()->prepare(
            'SELECT COUNT(*) AS n FROM login_attempts
              WHERE (email = ? OR ip = ?)
                AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)'
        );
        $stmt->execute([$email, self::clientIp(), self::WINDOW_MINUTES]);
        return ((int) ($stmt->fetch()['n'] ?? 0)) >= self::MAX_ATTEMPTS;
    }

    /** Registra un intento FALLIDO. Los aciertos no se guardan. */
    public static function recordFailure(string $email): void
    {
        getDB()->prepare(
            'INSERT INTO login_attempts (email, ip, created_at) VALUES (?, ?, NOW())'
        )->execute([$email, self::clientIp()]);
    }

    /** Limpia los intentos tras un acceso correcto. */
    public static function clearAttempts(string $email): void
    {
        getDB()->prepare('DELETE FROM login_attempts WHERE email = ? OR ip = ?')
               ->execute([$email, self::clientIp()]);
    }

    public static function lockMinutes(): int
    {
        return self::WINDOW_MINUTES;
    }
}
