<?php

declare(strict_types=1);

/**
 * Router con regex cruda.
 *
 * El matching es secuencial con preg_match, así que las rutas literales
 * ('/cuponeras/nueva') tienen que registrarse ANTES que las parametrizadas
 * ('/cuponeras/(\d+)'), o la parametrizada se las come.
 *
 * Los grupos capturados se pasan posicionalmente al método del controlador.
 */
class Router
{
    private array $routes = [];

    public function get(string $path, string $controller, string $action): void
    {
        $this->routes[] = ['GET', $path, $controller, $action];
    }

    public function post(string $path, string $controller, string $action): void
    {
        $this->routes[] = ['POST', $path, $controller, $action];
    }

    /** Ruta actual, ya sin el prefijo BASE_URL. */
    public static function currentUri(): string
    {
        $uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = rtrim(BASE_URL, '/');
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . ltrim($uri, '/');
        return $uri === '/' ? '/' : rtrim($uri, '/');
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = self::currentUri();

        foreach ($this->routes as [$routeMethod, $routePath, $controller, $action]) {
            if ($routeMethod !== $method) continue;

            if (preg_match('#^' . $routePath . '$#u', $uri, $matches)) {
                $params = array_slice($matches, 1);
                (new $controller())->$action(...$params);
                return;
            }
        }

        http_response_code(404);
        (new Controller())->render('errors/404', ['uri' => $uri], 'main');
    }
}
