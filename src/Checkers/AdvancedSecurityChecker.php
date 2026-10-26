<?php
declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

use SatuRumah\Forensic\Core\FileSystem;

class AdvancedSecurityChecker extends AbstractChecker {
    public function getName(): string { return 'Advanced Security & Runtime'; }

    public function run(): array {
        $files = FileSystem::scanDir($this->config->getTargetDir(), ['php'], ['vendor', 'node_modules']);
        
        // 1. File Upload (Deep Analysis)
        $uploadIssues = [];
        foreach ($files as $f) {
            $c = @file_get_contents($f); if (!$c) continue;
            $rel = substr($f, strlen($this->config->getTargetDir()) + 1);
            if (preg_match('/move_uploaded_file\s*\(/i', $c)) {
                $issues = [];
                if (!preg_match('/mime_content_type|finfo_file|getimagesize|exif_imagetype/i', $c)) $issues[] = 'no magic-byte validation';
                if (!preg_match('/pathinfo\s*\(.*?\bEXTENSION\b/i', $c)) $issues[] = 'no extension check';
                if (preg_match('/\$_FILES\s*\[[^\]]+\]\s*\[["\']name["\']\]/', $c) && !preg_match('/basename|preg_replace.*?[\w\W]/i', $c)) $issues[] = 'uses raw filename (path traversal risk)';
                if (!empty($issues)) $uploadIssues[] = "{$rel}: " . implode(', ', $issues);
            }
        }
        if (!empty($uploadIssues)) $this->addResult('WARN', count($uploadIssues) . ' file upload vulnerabilities.', ['list' => $uploadIssues]);
        else $this->addResult('PASS', 'File uploads look secure (magic-bytes + extension check).');

        // 2. API/CORS/JWT Deep Analysis
        $corsIssues = [];
        foreach ($files as $f) {
            $c = @file_get_contents($f); if (!$c) continue;
            $rel = substr($f, strlen($this->config->getTargetDir()) + 1);
            if (preg_match('/Access-Control-Allow-Origin\s*:\s*\*/i', $c)) $corsIssues[] = "{$rel}: Wildcard CORS (*)";
            if (preg_match('/(api[_-]?key|secret[_-]?key|bearer)\s*=\s*["\'][a-zA-Z0-9]{20,}["\']/i', $c)) $corsIssues[] = "{$rel}: Hardcoded API key/secret";
            if (preg_match('/jwt_decode\s*\(/i', $c) && !preg_match('/verify|signature|leeway/i', $c)) $corsIssues[] = "{$rel}: JWT decoded WITHOUT signature verification";
            if (preg_match('/["\']none["\']\s*[,\)]\s*[^,]*\bverify/i', $c)) $corsIssues[] = "{$rel}: JWT 'none' algorithm accepted";
        }
        if (!empty($corsIssues)) $this->addResult('WARN', count($corsIssues) . ' API/CORS/JWT issues.', ['list' => $corsIssues]);
        else $this->addResult('PASS', 'No API/CORS/JWT issues detected.');

        // 3. Runtime (Headers & Cookies)
        if (function_exists('curl_init')) {
            $this->runRuntimeCheck();
        } else {
            $this->addResult('SKIP', 'cURL extension missing. Skipping runtime checks.');
        }
        return $this->results;
    }

    private function runRuntimeCheck(): void {
        $entryPoints = ['public/index.php', 'index.php', 'router-dev.php', 'router.php', 'app.php'];
        $entry = null;
        foreach ($entryPoints as $ep) {
            if (file_exists($this->config->getTargetDir() . '/' . $ep)) { $entry = $ep; break; }
        }
        if (!$entry) { $this->addResult('SKIP', 'No suitable entry point found for runtime check.'); return; }

        $port = rand(9000, 9999);
        $targetDir = escapeshellarg($this->config->getTargetDir());
        $entryFile = escapeshellarg($this->config->getTargetDir() . '/' . $entry);
        
        $this->addResult('INFO', "Starting PHP server on port {$port} using {$entry}...");
        exec("php -S 127.0.0.1:{$port} -t {$targetDir} {$entryFile} > /dev/null 2>&1 &");
        sleep(1);

        $ch = curl_init("http://127.0.0.1:{$port}/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        exec("pkill -f 'php -S 127.0.0.1:{$port}'");

        if (!$response || $httpCode === 0) {
            $this->addResult('SKIP', 'Runtime server failed to respond.');
            return;
        }

        $this->addResult('PASS', "Runtime server responded (HTTP {$httpCode}).");

        $headers = ['X-Frame-Options', 'Content-Security-Policy', 'Strict-Transport-Security', 'X-Content-Type-Options', 'Referrer-Policy'];
        foreach ($headers as $h) {
            if (stripos($response, $h) !== false) $this->addResult('PASS', "Header present: {$h}");
            else $this->addResult('WARN', "Header missing: {$h}");
        }

        if (preg_match_all('/^Set-Cookie:\s*([^\r\n]+)/mi', $response, $matches)) {
            $this->addResult('INFO', 'Cookies received: ' . count($matches[1]));
            foreach ($matches[1] as $cookieStr) {
                $parts = explode(';', $cookieStr);
                $nameValue = trim(array_shift($parts));
                $flags = array_map('trim', $parts);
                
                $missing = [];
                if (!preg_grep('/HttpOnly/i', $flags)) $missing[] = 'HttpOnly';
                if (!preg_grep('/Secure/i', $flags)) $missing[] = 'Secure (wajib di production HTTPS)';
                if (!preg_grep('/SameSite/i', $flags)) $missing[] = 'SameSite';
                
                if (empty($missing)) $this->addResult('PASS', "Cookie [{$nameValue}] has all flags.");
                elseif (count($missing) === 1 && str_contains($missing[0], 'Secure')) $this->addResult('INFO', "Cookie [{$nameValue}] missing Secure (OK di localhost, wajib di prod)", ['list' => ["Cookie: {$nameValue}", "Flags: " . implode('; ', $flags)]]);
                else $this->addResult('WARN', "Cookie [{$nameValue}] missing: " . implode(', ', $missing), ['list' => ["Cookie: {$nameValue}", "Flags: " . implode('; ', $flags)]]);
            }
        } else {
            $this->addResult('INFO', 'No Set-Cookie headers found.');
        }
    }
}