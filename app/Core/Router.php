<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, array<int, array{pattern: string, handler: callable}>> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function add(string $method, string $path, callable $handler): void
    {
        $pattern = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static fn (): string => '([^/]+)', $path);
        $this->routes[strtoupper($method)][] = [
            'pattern' => '#^' . rtrim($pattern, '/') . '/?$#',
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $method = strtoupper($method);

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['pattern'], $path, $matches) === 1) {
                array_shift($matches);
                ($route['handler'])(...array_map('urldecode', $matches));
                return;
            }
        }

        http_response_code(404);
        View::render('errors/404', ['title' => 'Page not found'], 'guest');
    }
}
