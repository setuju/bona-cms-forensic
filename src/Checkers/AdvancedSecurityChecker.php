<?php
declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

use SatuRumah\Forensic\Core\FileSystem;

class AdvancedSecurityChecker extends AbstractChecker {
    public function getName(): string { return 'Advanced Security & Runtime'; }

    public function run(): array {
        $files = FileSystem::scanDir($this->config->getTargetDir(), ['php'], ['vendor', 'node_modules']);

        // 1. File Upload
        $uploadIssues = [];
        foreach ($files as $f) {
            $c = @file_get_contents($f); if (!$c) continue;
            if (preg_match('/move_uploaded_file\s*\(/i', $c) && !preg_match('/mime_content_type|finfo_file|getimagesize/i', $c)) {
                $rel = substr($f, strlen($this->config->getTargetDir()) + 1);
                $uploadIssues[] = "{$rel}: move_uploaded_file without magic-byte validation";
            }
        }
        if (!empty($uploadIssues)) { $this->addResult('WARN', count($uploadIssues) . ' insecure file uploads.', ['list' => $uploadIssues]); }
        else { $this->addResult('PASS', 'No insecure file uploads detected.'); }

        // 2. API & CORS
        $corsIssues = [];
        foreach ($files as $f) {
            $c = @file_get_contents($f); if (!$c) continue;
            $rel = substr($f, strlen($this->config->getTargetDir()) + 1);
            if (preg_match('/Access-Control-Allow-Origin\s*:\s*\*/i', $c)) $corsIssues[] = "{$rel}: Wildcard CORS";
            if (preg_match('/(api[_-]?key|secret[_-]?key|bearer)\s*=\s*["\'][a-zA-Z0-9]{20,}["\']/i', $c)) $corsIssues[] = "{$rel}: Hardcoded API key";
        }
        if (!empty($corsIssues)) { $this->addResult('WARN', count($corsIssues) . ' API/CORS issues.', ['list' => $corsIssues]); }
        else { $this->addResult('PASS', 'No API/CORS issues detected.'); }

        // 3. Runtime Check (Headers & Cookies)
        if (function_exists('curl_init')) {
            $port = rand(9000, 9999);
            exec("php -S 127.0.0.1:{$port} -t " . escapeshellarg($this->config->getTargetDir()) . " > /dev/null 2>&1 &");
            sleep(1);
            $ch = curl_init("http://127.0.0.1:{$port}/");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            $response = curl_exec($ch);
            curl_close($ch);
            exec("pkill -f 'php -S 127.0.0.1:{$port}'");

            if ($response) {
                $this->addResult('PASS', 'Runtime server responded.');
                $headers = ['X-Frame-Options', 'Content-Security-Policy', 'Strict-Transport-Security'];
                foreach ($headers as $h) {
                    if (stripos($response, $h) !== false) $this->addResult('PASS', "Header present: {$h}");
                    else $this->addResult('WARN', "Header missing: {$h}");
                }
            } else {
                $this->addResult('SKIP', 'Could not connect to local server for runtime check.');
            }
        } else {
            $this->addResult('SKIP', 'cURL extension missing. Skipping runtime checks.');
        }

        return $this->results;
    }
}