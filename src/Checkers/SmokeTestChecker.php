<?php
declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

class SmokeTestChecker extends AbstractChecker {
    public function getName(): string { return 'Smoke Test (HTTP Endpoints)'; }

    public function run(): array {
        if (!function_exists('curl_init')) {
            $this->addResult('SKIP', 'cURL tidak tersedia. Smoke test dilewati.');
            return $this->results;
        }

        $entryPoints = ['public/index.php', 'index.php', 'router-dev.php'];
        $entry = null;
        foreach ($entryPoints as $ep) {
            if (file_exists($this->config->getTargetDir() . '/' . $ep)) { $entry = $ep; break; }
        }
        if (!$entry) {
            $this->addResult('SKIP', 'Tidak ada entry point untuk smoke test.');
            return $this->results;
        }

        // Daftar endpoint yang akan dites
        $endpoints = [
            '/' => 'Homepage',
            '/admin/login.php' => 'Admin Login',
            '/api/posts.php' => 'API Posts',
            '/subscribe.php' => 'Subscribe',
        ];

        $port = rand(9100, 9999);
        $targetDir = escapeshellarg($this->config->getTargetDir());
        exec("php -S 127.0.0.1:{$port} -t {$targetDir} > /dev/null 2>&1 &");
        sleep(1);

        $results = []; $failCount = 0;
        foreach ($endpoints as $path => $label) {
            $url = "http://127.0.0.1:{$port}{$path}";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code === 200 || $code === 302) {
                $results[] = "{$label} ({$path}) → HTTP {$code} ✅";
            } elseif ($code === 404) {
                $results[] = "{$label} ({$path}) → HTTP 404 ❌ (Not Found)";
                $failCount++;
            } elseif ($code === 500) {
                $results[] = "{$label} ({$path}) → HTTP 500 ❌ (Server Error)";
                $failCount++;
            } else {
                $results[] = "{$label} ({$path}) → HTTP {$code} ⚠️";
            }
        }

        exec("pkill -f 'php -S 127.0.0.1:{$port}'");

        $this->addResult('INFO', "Smoke test pada {$port}: " . count($endpoints) . " endpoint dites.");
        if ($failCount > 0) {
            $this->addResult('FAIL', "{$failCount} endpoint gagal (404/500).", ['list' => $results]);
        } else {
            $this->addResult('PASS', 'Semua endpoint merespons dengan baik.', ['list' => $results]);
        }
        return $this->results;
    }
}