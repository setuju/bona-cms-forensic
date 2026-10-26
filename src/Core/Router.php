<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  File      : src/Core/Router.php                                     │
 * │  Purpose   : Basic routing engine                                    │
 * └──────────────────────────────────────────────────────────────────────┘
 */
declare(strict_types=1);
namespace SatuRumah\Core;

class Router {
    private array $routes = [];
    
    public function add(string $path, callable $handler): void {
        $this->routes[$path] = $handler;
    }
    
    public function dispatch(string $uri): void {
        $path = parse_url($uri, PHP_URL_PATH);
        if (isset($this->routes[$path])) {
            call_user_func($this->routes[$path]);
        } else {
            http_response_code(404);
            echo "404 Not Found";
        }
    }
}
