<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  File      : scripts/mock-server.php                                 │
 * │  Purpose   : Mock server to test security headers & cookies          │
 * └──────────────────────────────────────────────────────────────────────┘
 */
// Set security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'self\'');

// Set secure cookie
setcookie('bona_session', 'test123', [
    'httponly' => true,
    'secure' => true,
    'samesite' => 'Lax'
]);

echo "<h1>Bona Mock Server</h1>";
echo "<p>Security headers and cookies are set for testing.</p>";
