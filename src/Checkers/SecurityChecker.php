<?php
declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

use SatuRumah\Forensic\Core\FileSystem;

class SecurityChecker extends AbstractChecker {
    public function getName(): string { return 'Security Heuristics'; }

    public function run(): array {
        $files = FileSystem::scanDir($this->config->getTargetDir(), ['php']);
        $rules = [
            ['SQLi', '/\b(SELECT|INSERT|UPDATE|DELETE)\b.*?\$_(GET|POST|REQUEST|COOKIE)/i'],
            ['XSS', '/echo\s+(?!.*?(?:htmlspecialchars|e\(|urlencode)).*?\$_(GET|POST|REQUEST|COOKIE)/i'],
            ['RCE', '/(?<!->)\b(system|exec|shell_exec|passthru|popen|proc_open|eval)\s*\(/i']
        ];

        $findings = 0;
        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if (!$content) continue;
            foreach (explode("\n", $content) as $lineNum => $line) {
                if (str_starts_with(ltrim($line), '//')) continue;
                foreach ($rules as [$name, $regex]) {
                    if (preg_match($regex, $line)) {
                        $findings++;
                        $this->addResult('FAIL', "{$name} detected", ['file' => ltrim(str_replace($this->config->getTargetDir(), '', $file), '/'), 'line' => $lineNum + 1]);
                    }
                }
            }
        }

        if ($findings === 0) {
            $this->addResult('PASS', 'No critical security issues detected.');
        }
        return $this->results;
    }
}