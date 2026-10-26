<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Checkers/AssetChecker.php
 * │  Purpose   : Static asset inventory (size, distribution, large files)
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

class AssetChecker extends AbstractChecker {
    /** @var array<int, string> */
    private array $assetExts = ['js', 'css', 'png', 'jpg', 'jpeg', 'svg', 'webp', 'woff', 'woff2'];

    /** @var array<int, string> */
    private array $excludeDirs = ['vendor', 'node_modules', '.git', '.bona', 'dist', 'build', 'cache', 'storage', 'tmp'];

    private const LARGE_THRESHOLD_BYTES = 500 * 1024;
    private const TOTAL_FAIL_MB = 50.0;

    public function getName(): string { return 'Asset Performance'; }

    public function run(): array {
        $targetDir = $this->config->getTargetDir();
        $files = FileSystem::scanDir($targetDir, $this->assetExts, $this->excludeDirs);

        if (empty($files)) {
            $this->addResult('SKIP', 'No asset files found.');
            return $this->results;
        }

        /** @var array<int, int> $sizes */
        $sizes = [];
        /** @var array<int, array{rel: string, size: int}> $large */
        $large = [];

        foreach ($files as $f) {
            $sz = @filesize($f);
            if ($sz === false) continue;
            $sizes[] = $sz;
            if ($sz > self::LARGE_THRESHOLD_BYTES) {
                $rel = substr($f, strlen($targetDir) + 1);
                $large[] = ['rel' => $rel, 'size' => $sz];
            }
        }

        if (empty($sizes)) {
            $this->addResult('SKIP', 'No readable asset files.');
            return $this->results;
        }

        $count = count($sizes);
        $totalBytes = array_sum($sizes);
        $totalMB = $totalBytes / 1024 / 1024;
        $meanKB = ($totalBytes / $count) / 1024;
        $medianKB = $this->median($sizes) / 1024;
        $sdKB = $this->stdDev($sizes) / 1024;

        $summary = sprintf(
            '%d asset(s), %.2f MB total, mean %.1f KB, median %.1f KB, SD %.1f KB',
            $count, $totalMB, $meanKB, $medianKB, $sdKB
        );

        /** @var array{list: array<int, string>} $details */
        $details = ['list' => []];
        if (!empty($large)) {
            usort($large, static fn(array $a, array $b): int => $b['size'] <=> $a['size']);
            foreach (array_slice($large, 0, 10) as $a) {
                $details['list'][] = sprintf('%s (%.0f KB)', $a['rel'], $a['size'] / 1024);
            }
            if (count($large) > 10) {
                $details['list'][] = '... and ' . (count($large) - 10) . ' more';
            }
            $details['list'][] = sprintf('Total: %d file(s) > %d KB', count($large), (int)(self::LARGE_THRESHOLD_BYTES / 1024));
        }

        if ($totalMB > self::TOTAL_FAIL_MB) {
            $this->addResult('FAIL', sprintf('Total asset size %.2f MB exceeds %.0f MB budget', $totalMB, self::TOTAL_FAIL_MB), $details);
        } elseif (!empty($large)) {
            $this->addResult('WARN', sprintf('%d large asset(s) > %d KB — %s', count($large), (int)(self::LARGE_THRESHOLD_BYTES / 1024), $summary), $details);
        } else {
            $this->addResult('PASS', $summary);
        }

        return $this->results;
    }

    /** @param array<int, int> $values */
    private function median(array $values): float {
        if (empty($values)) return 0.0;
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);
        return ($n % 2) ? (float)$values[$mid] : (($values[$mid - 1] + $values[$mid]) / 2.0);
    }

    /** @param array<int, int> $values */
    private function stdDev(array $values): float {
        $n = count($values);
        if ($n < 2) return 0.0;
        $mean = array_sum($values) / $n;
        $variance = 0.0;
        foreach ($values as $v) $variance += ($v - $mean) ** 2;
        return sqrt($variance / ($n - 1));
    }
}
