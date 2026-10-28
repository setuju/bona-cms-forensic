<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Checkers/CoverageChecker.php
 * │  Purpose   : PHPUnit line coverage audit (pcov/xdebug)
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

class CoverageChecker extends AbstractChecker {
    public function getName(): string { return 'Code Coverage (PHPUnit)'; }

    private const WARN_BELOW = 80.0;
    private const FAIL_BELOW = 60.0;
    private const TIMEOUT = 300;

    public function run(): array {
        $driver = $this->coverageDriver();
        if ($driver === null) {
            $this->addResult('SKIP', 'No coverage driver (pcov or xdebug). Install: sudo pecl install pcov');
            return $this->results;
        }

        $root = $this->config->getTargetDir();
        $phpunit = $root . '/vendor/bin/phpunit';
        if (!is_file($phpunit)) {
            $this->addResult('SKIP', 'vendor/bin/phpunit not found — no PHPUnit installed.');
            return $this->results;
        }
        if (!is_file($root . '/phpunit.xml') && !is_file($root . '/phpunit.xml.dist')) {
            $this->addResult('SKIP', 'No phpunit.xml — cannot configure coverage run.');
            return $this->results;
        }

        $cmd = 'cd ' . escapeshellarg($root)
             . ' && timeout ' . self::TIMEOUT
             . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phpunit)
             . ' --coverage-text --colors=never --do-not-cache-result 2>&1';

        $out = @shell_exec($cmd);
        if (!is_string($out) || $out === '') {
            $this->addResult('SKIP', 'phpunit produced no output (timeout or crash).');
            return $this->results;
        }

        if (!preg_match('/Lines:\s+([\d.]+)%\s*\((\d+)\/(\d+)\)/', $out, $m)) {
            $this->addResult('SKIP', 'Could not parse coverage percentage from phpunit output.');
            return $this->results;
        }

        $pct = (float)$m[1];
        $covered = (int)$m[2];
        $total = (int)$m[3];
        $summary = sprintf('Line coverage: %.2f%% (%d/%d) via %s', $pct, $covered, $total, $driver);

        if ($pct < self::FAIL_BELOW) {
            $this->addResult('FAIL', $summary . ' — below ' . self::FAIL_BELOW . '% critical threshold.');
        } elseif ($pct < self::WARN_BELOW) {
            $this->addResult('WARN', $summary . ' — below ' . self::WARN_BELOW . '% target.');
        } else {
            $this->addResult('PASS', $summary);
        }

        return $this->results;
    }

    private function coverageDriver(): ?string {
        if (extension_loaded('pcov')) return 'pcov';
        if (extension_loaded('xdebug')) return 'xdebug';
        return null;
    }
}
