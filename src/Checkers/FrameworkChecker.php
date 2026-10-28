<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Checkers/FrameworkChecker.php
 * │  Purpose   : Framework detection (WordPress / Laravel / Symfony /
 * │              CodeIgniter / Slim / Yii / CakePHP / Drupal)
 * │  Author    : Jack — Jack[at]SatuRumah[dot]Net                        │
 * │  GitHub    : https://github.com/setuju                               │
 * │  Website   : https://saturumah.net                                   │
 * │  Created   : 2026-10-28                                              │
 * │  License   : Proprietary — all rights reserved                       │
 * └──────────────────────────────────────────────────────────────────────┘
 *
 * @author    Jack <Jack[at]SatuRumah[dot]Net>
 * @created   2026-10-28
 * @package   SatuRumah\Forensic\Checkers
 * @license   Proprietary
 */

declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

class FrameworkChecker extends AbstractChecker {
    public function getName(): string { return 'Framework Detection'; }

    /** Marker files/dirs per framework (any match = detection) */
    private const MARKERS = [
        'WordPress'    => ['wp-config.php', 'wp-config-sample.php', 'wp-content', 'wp-admin', 'wp-includes'],
        'Laravel'      => ['artisan', 'bootstrap/app.php', 'config/app.php', 'app/Http/Kernel.php'],
        'Symfony'      => ['symfony.lock', 'bin/console', 'src/Kernel.php', 'config/bundles.php'],
        'CodeIgniter'  => ['application/config/config.php', 'system/core/CodeIgniter.php', 'app/Config/App.php'],
        'Yii2'         => ['yii', 'config/web.php', 'commands/HelloController.php'],
        'CakePHP'      => ['bin/cake', 'src/Application.php', 'config/app.php'],
        'Drupal'       => ['core/lib/Drupal.php', 'sites/default/settings.php'],
        'Slim'         => ['public/index.php'],
    ];

    /** Composer dependencies that imply framework */
    private const COMPOSER_DEPS = [
        'slim/slim'                 => 'Slim',
        'laravel/framework'         => 'Laravel',
        'symfony/framework-bundle'  => 'Symfony',
        'codeigniter4/framework'    => 'CodeIgniter',
        'yiisoft/yii2'              => 'Yii2',
        'cakephp/cakephp'           => 'CakePHP',
        'drupal/core'               => 'Drupal',
    ];

    public function run(): array {
        $root = $this->config->getTargetDir();
        $detected = [];

        foreach (self::MARKERS as $fw => $paths) {
            $matched = [];
            foreach ($paths as $p) {
                if (file_exists($root . '/' . $p)) $matched[] = $p;
            }
            if (!empty($matched)) {
                $detected[$fw] = $matched;
            }
        }

        $composer = $root . '/composer.json';
        if (is_readable($composer)) {
            $raw = @file_get_contents($composer);
            if ($raw !== false) {
                $c = json_decode($raw, true);
                if (is_array($c)) {
                    $deps = array_merge($c['require'] ?? [], $c['require-dev'] ?? []);
                    foreach (self::COMPOSER_DEPS as $pkg => $fw) {
                        if (isset($deps[$pkg])) {
                            $detected[$fw][] = "composer: {$pkg}";
                        }
                    }
                }
            }
        }

        if (empty($detected)) {
            $this->addResult('INFO', 'No framework markers detected (vanilla PHP or custom codebase).');
            return $this->results;
        }

        // Sort by evidence count desc (most confident first)
        uasort($detected, fn($a, $b) => count($b) <=> count($a));

        foreach ($detected as $fw => $evidence) {
            $unique = array_values(array_unique($evidence));
            $this->addResult('INFO', sprintf(
                'Detected: %s (%d marker%s) — %s',
                $fw,
                count($unique),
                count($unique) === 1 ? '' : 's',
                implode(', ', array_slice($unique, 0, 5))
            ), ['list' => $unique]);
        }

        return $this->results;
    }
}
