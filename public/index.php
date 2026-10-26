<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  File      : public/index.php                                        │
 * │  Purpose   : Application Entry Point                                 │
 * └──────────────────────────────────────────────────────────────────────┘
 */
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';

use SatuRumah\Core\Router;

$router = new Router();
$router->add('/', function() {
    echo "Welcome to Bona CMS. Secure by Default.";
});
$router->dispatch($_SERVER['REQUEST_URI']);
