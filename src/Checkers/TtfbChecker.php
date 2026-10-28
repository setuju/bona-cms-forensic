<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Checkers/TtfbChecker.php
 * │  Purpose   : Time To First Byte (TTFB) runtime measurement tracker   │
 * │              (v2.2.0 skeleton — returns SKIP)                        │
 * │  Author    : Jack — Jack[at]SatuRumah[dot]Net                        │
 * │  GitHub    : https://github.com/setuju                               │
 * │  Website   : https://saturumah.net                                   │
 * │  Created   : 2026-10-26                                              │
 * │  License   : Proprietary — all rights reserved                       │
 * └──────────────────────────────────────────────────────────────────────┘
 *
 * @author    Jack <Jack[at]SatuRumah[dot]Net>
 * @created   2026-10-26
 * @package   SatuRumah\Forensic\Checkers
 * @license   Proprietary
 */

declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

class TtfbChecker extends AbstractChecker {
    public function getName(): string { return 'TTFB Tracker (Time To First Byte)'; }

    private const SAMPLES = 5;
    private const WARN_RATIO = 1.10;
    private const FAIL_RATIO = 1.20;
    private const BASELINE_FILE = 'ttfb-baseline.json';

    public function run(): array {
        if (!function_exists('curl_init')) {
            $this->addResult('SKIP', 'TTFB measurement requires ext-curl (not installed).');
            return $this->results;
        }

        $entry = $this->detectEntryPoint();
        if ($entry === null) {
            $this->addResult('SKIP', 'No entry point found (index.php / public/index.php / router.php).');
            return $this->results;
        }

        $port = $this->findFreePort();
        if ($port === null) {
            $this->addResult('SKIP', 'No free port found in range 9297-9397.');
            return $this->results;
        }

        if (!$this->startServer($port, $entry)) {
            $this->stopServer($port);
            $this->addResult('SKIP', "PHP built-in server failed to start on port {$port}.");
            return $this->results;
        }

        try {
            $measurements = $this->measureTtfb($port);
        } finally {
            $this->stopServer($port);
        }

        if (count($measurements) < 3) {
            $this->addResult('SKIP', 'Insufficient samples (' . count($measurements) . '/' . self::SAMPLES . ') — server did not respond reliably.');
            return $this->results;
        }

        $median = $this->median($measurements);
        $baselinePath = $this->config->getToolDir() . '/.bona/' . self::BASELINE_FILE;
        $baseline = $this->loadBaseline($baselinePath);
        $n = count($measurements);

        $this->writeLog($entry, $port, $measurements, $median, $baseline);

        if ($baseline === null) {
            $this->saveBaseline($baselinePath, $median);
            $this->addResult('PASS', sprintf('TTFB baseline established: %.0fms (median, n=%d).', $median * 1000, $n));
            return $this->results;
        }

        $ratio = $baseline > 0.0 ? $median / $baseline : 1.0;
        $deltaPct = ($ratio - 1.0) * 100.0;
        $summary = sprintf('TTFB: %.0fms vs baseline %.0fms (%+.1f%%, n=%d)', $median * 1000, $baseline * 1000, $deltaPct, $n);

        if ($ratio > self::FAIL_RATIO) {
            $this->addResult('FAIL', $summary . ' — regression > 20%');
        } elseif ($ratio > self::WARN_RATIO) {
            $this->addResult('WARN', $summary . ' — regression > 10%');
        } else {
            if ($median < $baseline) {
                $this->saveBaseline($baselinePath, $median);
                $summary .= ' — baseline updated';
            }
            $this->addResult('PASS', $summary);
        }

        return $this->results;
    }

    private function detectEntryPoint(): ?string {
        $targetDir = $this->config->getTargetDir();
        foreach (['public/index.php', 'index.php', 'router-dev.php', 'router.php'] as $candidate) {
            $p = $targetDir . '/' . $candidate;
            if (is_file($p)) return $p;
        }
        return null;
    }

    private function findFreePort(): ?int {
        $tried = [];
        for ($i = 0; $i < 10; $i++) {
            $port = random_int(9297, 9397);
            if (in_array($port, $tried, true)) continue;
            $tried[] = $port;
            $sock = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if ($sock === false) return $port;
            @fclose($sock);
        }
        return null;
    }

    private function startServer(int $port, string $entry): bool {
        $targetDir = $this->config->getTargetDir();
        $cmd = 'php -S 127.0.0.1:' . $port . ' -t ' . escapeshellarg($targetDir) . ' ' . escapeshellarg($entry) . ' > /dev/null 2>&1 &';
        @exec($cmd);
        for ($i = 0; $i < 15; $i++) {
            usleep(200000);
            $ch = curl_init("http://127.0.0.1:{$port}/");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
            curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code > 0 && $code < 500) return true;
        }
        return false;
    }

    private function stopServer(int $port): void {
        @exec("pkill -f 'php -S 127.0.0.1:{$port}' 2>/dev/null");
    }

    /** @return array<int, float> */
    private function measureTtfb(int $port): array {
        /** @var array<int, float> $samples */
        $samples = [];
        for ($i = 0; $i < self::SAMPLES; $i++) {
            $ch = curl_init("http://127.0.0.1:{$port}/");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            $body = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $ttfb = (float)curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME);
            curl_close($ch);
            if ($body !== false && $code > 0 && $code < 500 && $ttfb > 0.0) {
                $samples[] = $ttfb;
            }
            usleep(100000);
        }
        return $samples;
    }

    /** @param array<int, float> $values */
    private function median(array $values): float {
        if (empty($values)) return 0.0;
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);
        return ($n % 2) ? (float)$values[$mid] : (($values[$mid - 1] + $values[$mid]) / 2.0);
    }

    private function loadBaseline(string $path): ?float {
        if (!is_file($path)) return null;
        $raw = @file_get_contents($path);
        if ($raw === false) return null;
        $data = @json_decode($raw, true);
        if (!is_array($data)) return null;
        $m = $data['median_seconds'] ?? null;
        return is_numeric($m) ? (float)$m : null;
    }

    private function saveBaseline(string $path, float $median): void {
        $dir = dirname($path);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $tmp = $path . '.tmp';
        $data = [
            'median_seconds' => round($median, 4),
            'updated_at' => date('c'),
            'samples' => self::SAMPLES,
        ];
        @file_put_contents($tmp, (string)json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        @rename($tmp, $path);
    }

    /** @param array<int, float> $measurements */
    private function writeLog(string $entry, int $port, array $measurements, float $median, ?float $baseline): void {
        $logDir = $this->config->getLogDir();
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        $samplesStr = implode(', ', array_map(static fn(float $s): string => round($s * 1000, 1) . 'ms', $measurements));
        $lines = [
            'timestamp: ' . date('c'),
            'target: ' . $this->config->getTargetDir(),
            'entry: ' . $entry,
            'port: ' . $port,
            'samples: ' . $samplesStr,
            'median_ms: ' . round($median * 1000, 1),
            'baseline_ms: ' . ($baseline !== null ? (string)round($baseline * 1000, 1) : 'none'),
        ];
        \SatuRumah\Forensic\Core\FileSystem::writeLog($logDir, 'ttfb', $lines);
    }
}
