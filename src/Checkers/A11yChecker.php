<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Checkers/A11yChecker.php
 * │  Purpose   : WCAG 2.1 AA accessibility audit via pa11y-ci            │
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

class A11yChecker extends AbstractChecker {
    public function getName(): string { return 'A11y (WCAG 2.1 AA) via pa11y-ci'; }

    private const HTTP_TIMEOUT_SEC = 120;
    private const PAGESPEED_PROBE_TIMEOUT = 3;

    public function run(): array {
        if (!function_exists('curl_init')) {
            $this->addResult('SKIP', 'A11y requires ext-curl.');
            return $this->results;
        }

        $pa11y = trim((string)shell_exec('which pa11y-ci 2>/dev/null'));
        if ($pa11y === '') {
            $this->addResult('SKIP', 'pa11y-ci not installed. Install: npm install -g pa11y-ci');
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
            $configPath = $this->writePa11yConfig();
            if ($configPath === null) {
                $this->addResult('SKIP', 'Failed to write pa11y-ci config to .bona/.');
                return $this->results;
            }

            $url = "http://127.0.0.1:{$port}/";
            $cmd = 'timeout ' . self::HTTP_TIMEOUT_SEC
                 . ' ' . escapeshellarg($pa11y)
                 . ' --config=' . escapeshellarg($configPath)
                 . ' --reporter=json '
                 . escapeshellarg($url)
                 . ' 2>/dev/null';
            $out = [];
            $rc = 0;
            @exec($cmd, $out, $rc);
            $stdout = implode("\n", $out);

            if ($rc === 124) {
                $this->addResult('SKIP', 'pa11y-ci timed out after ' . self::HTTP_TIMEOUT_SEC . 's.');
                return $this->results;
            }

            // pa11y-ci may emit stray text before JSON — extract from first '{'
            $jsonStart = strpos($stdout, '{');
            if ($jsonStart !== false && $jsonStart > 0) {
                $stdout = substr($stdout, $jsonStart);
            }
            $json = @json_decode($stdout, true);
            if (!is_array($json)) {
                $this->addResult('SKIP', 'pa11y-ci output not JSON-parseable (Chromium issue?). rc=' . $rc);
                return $this->results;
            }

            $errorCount = (int)($json['errors'] ?? 0);
            $passCount = (int)($json['passes'] ?? 0);

            $findings = [];
            if (isset($json['results']) && is_array($json['results'])) {
                foreach ($json['results'] as $urlKey => $issues) {
                    if (!is_array($issues)) continue;
                    foreach ($issues as $issue) {
                        if (($issue['type'] ?? '') !== 'error') continue;
                        $findings[] = sprintf(
                            '[%s] %s | %s',
                            (string)($issue['code'] ?? '?'),
                            (string)($issue['message'] ?? '?'),
                            (string)($issue['selector'] ?? '?')
                        );
                    }
                }
            }

            $this->writeLog($url, $errorCount, $passCount, $findings);

            if ($errorCount > 0) {
                $this->addResult('FAIL', "{$errorCount} WCAG 2.1 AA error(s) on {$url}.", ['list' => array_slice($findings, 0, 20)]);
            } elseif ($passCount > 0) {
                $this->addResult('PASS', "No WCAG 2.1 AA errors on {$url} ({$passCount} checks passed).");
            } else {
                $this->addResult('PASS', "No WCAG 2.1 AA errors detected on {$url}.");
            }
        } finally {
            $this->stopServer($port);
        }

        return $this->results;
    }

    private function detectEntryPoint(): ?string {
        $targetDir = $this->config->getTargetDir();
        foreach (['public/index.php', 'index.php', 'router.php', 'router-dev.php'] as $candidate) {
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
            curl_setopt($ch, CURLOPT_TIMEOUT, self::PAGESPEED_PROBE_TIMEOUT);
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

    private function writePa11yConfig(): ?string {
        $path = $this->config->getToolDir() . '/.bona/a11y-ci-config.json';
        $dir = dirname($path);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $cfg = [
            'defaults' => [
                'standard' => 'WCAG2AA',
                'timeout' => 30000,
                'chromeLaunchConfig' => [
                    'args' => ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
                ],
            ],
        ];
        $tmp = $path . '.tmp';
        $ok = @file_put_contents($tmp, (string)json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if ($ok === false) return null;
        @rename($tmp, $path);
        return $path;
    }

    /** @param array<int, string> $findings */
    private function writeLog(string $url, int $errorCount, int $passCount, array $findings): void {
        $logDir = $this->config->getLogDir();
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        $lines = [
            'timestamp: ' . date('c'),
            'target: ' . $this->config->getTargetDir(),
            'url: ' . $url,
            'errors: ' . $errorCount,
            'passes: ' . $passCount,
            'findings:',
        ];
        foreach ($findings as $f) $lines[] = '  - ' . $f;
        \SatuRumah\Forensic\Core\FileSystem::writeLog($logDir, 'a11y', $lines);
    }
}
