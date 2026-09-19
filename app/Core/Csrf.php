<?php

declare(strict_types=1);

class Csrf
{
    /** Token de la sesión, creado la primera vez que se pide. */
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** Campo oculto listo para meter en un formulario. */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::token()) . '">';
    }

    /** Comparación en tiempo constante. */
    public static function verify(?string $token): bool
    {
        return is_string($token)
            && !empty($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Exige un token válido o corta con 403.
     *
     * Acepta el campo del formulario y la cabecera X-CSRF-Token que manda el JS
     * leyéndola de <meta name="csrf-token">.
     */
    public static function require(): void
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if ($token === null) {
            $raw = file_get_contents('php://input') ?: '';
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) $token = $decoded['csrf_token'] ?? null;
            }
        }

        if (!self::verify(is_string($token) ? $token : null)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'data'   => null,
                'error'  => 'Token de seguridad inválido. Recarga la página.',
                'status' => 403,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
