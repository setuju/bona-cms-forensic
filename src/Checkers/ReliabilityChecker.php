<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Checkers/ReliabilityChecker.php
 * │  Purpose   : Quality baseline metrics (tests, phpunit config, deps)
 * │  Author    : Jack — Jack[at]SatuRumah[dot]Net                        │
 * │  GitHub    : https://github.com/setuju                               │
 * │  Website   : https://saturumah.net                                   │
 * │  Created   : 2026-10-26                                              │
 * │  License   : Proprietary — all rights reserved                       │
 * │                                                                      │
 * │  This header is the file contract. If it is missing, the file is     │
 * │  vendored, generated, or outside the project boundary.               │
 * └──────────────────────────────────────────────────────────────────────┘
 *
 * @author    Jack <Jack[at]SatuRumah[dot]Net>
 * @created   2026-10-26
 * @package   SatuRumah\Forensic\Checkers
 * @license   Proprietary
 */

declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

use SatuRumah\Forensic\Core\FileSystem;

class ReliabilityChecker extends AbstractChecker {
    public function getName(): string { return 'Code Reliability'; }

    public function run(): array {
        $targetDir = $this->config->getTargetDir();

        $hasTestDir = false;
        $testCount = 0;
        foreach (['tests', 'test'] as $d) {
            $p = $targetDir . '/' . $d;
            if (is_dir($p)) {
                $hasTestDir = true;
                $testCount += count(FileSystem::scanDir($p, ['php']));
            }
        }

        $hasPhpUnit = file_exists($targetDir . '/phpunit.xml')
            || file_exists($targetDir . '/phpunit.xml.dist');

        $hasComposer = false;
        $prodDeps = 0;
        $devDeps = 0;
        $composerPath = $targetDir . '/composer.json';
        if (file_exists($composerPath)) {
            $raw = @file_get_contents($composerPath);
            if ($raw !== false) {
                $j = @json_decode($raw, true);
                if (is_array($j)) {
                    $hasComposer = true;
                    $prodDeps = isset($j['require']) && is_array($j['require']) ? count($j['require']) : 0;
                    $devDeps = isset($j['require-dev']) && is_array($j['require-dev']) ? count($j['require-dev']) : 0;
                }
            }
        }

        if (!$hasTestDir && !$hasPhpUnit && !$hasComposer) {
            $this->addResult('SKIP', 'No composer.json, phpunit config, or test dir found.');
            return $this->results;
        }

        $summary = sprintf(
            'tests=%d file(s), phpunit.xml=%s, deps=%d prod / %d dev',
            $testCount,
            $hasPhpUnit ? 'yes' : 'no',
            $prodDeps,
            $devDeps
        );

        /** @var array<int, string> $warnings */
        $warnings = [];
        if ($hasTestDir && $testCount === 0) $warnings[] = 'tests/ dir exists but no PHP test files';
        if ($hasComposer && !$hasTestDir)      $warnings[] = 'no tests/ directory (test gap)';
        if ($hasTestDir && !$hasPhpUnit)       $warnings[] = 'tests present but phpunit.xml missing';
        if ($prodDeps > 50)                    $warnings[] = sprintf('%d prod deps (>50, dependency risk)', $prodDeps);

        if (!empty($warnings)) {
            $this->addResult('WARN', $summary, ['list' => $warnings]);
        } else {
            $this->addResult('PASS', $summary);
        }
        return $this->results;
    }
}
