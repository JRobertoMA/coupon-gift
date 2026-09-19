<?php

declare(strict_types=1);

class Controller
{
    /** Renderiza una vista envuelta en un layout. */
    public function render(string $view, array $data = [], string $layout = 'main'): void
    {
        if (!isset($data['_authUser'])) {
            $data['_authUser'] = Auth::userData();
        }
        extract($data);

        $viewFile   = BASE_PATH . '/app/Views/' . $view . '.php';
        $layoutFile = BASE_PATH . '/app/Views/layouts/' . $layout . '.php';

        ob_start();
        if (is_file($viewFile)) {
            require $viewFile;
        } else {
            echo '<p>Vista no encontrada: <code>' . htmlspecialchars($view) . '</code></p>';
        }
        $content = ob_get_clean();

        require $layoutFile;
    }

    /** Redirige a una ruta relativa a BASE_URL. */
    public function redirect(string $path): void
    {
        header('Location: ' . BASE_URL . $path);
        exit;
    }

    /** Responde JSON con el envelope del repo y termina. */
    public function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'data'   => $data,
            'error'  => null,
            'status' => $status,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Responde un error JSON con el envelope del repo y termina. */
    public function jsonError(string $message, int $status = 422): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'data'   => null,
            'error'  => $message,
            'status' => $status,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 404 y fin.
     *
     * También se usa cuando el recurso existe pero no es del usuario: así no se
     * filtra que exista (nunca 403 para recursos ajenos).
     */
    public function notFound(): void
    {
        http_response_code(404);
        $this->render('errors/404', ['uri' => Router::currentUri()]);
        exit;
    }

    /** Guarda un mensaje flash que el layout pinta como Toast de Bootstrap. */
    public function flash(string $text, string $type = 'success'): void
    {
        $_SESSION['message'] = ['text' => $text, 'type' => $type];
    }

    /** Lee el cuerpo de la petición, sea JSON o form-urlencoded. */
    protected function body(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw !== '' && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) return $decoded;
        }
        return $_POST;
    }
}
