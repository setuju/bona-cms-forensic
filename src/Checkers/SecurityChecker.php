<?php
declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

use SatuRumah\Forensic\Core\FileSystem;

class SecurityChecker extends AbstractChecker {
    public function getName(): string { return 'Security Heuristics'; }

    public function run(): array {
        $files = FileSystem::scanDir($this->config->getTargetDir(), ['php'], ['vendor', 'node_modules', '.git']);
        $rules = [
            'SQLi'     => '/\b(SELECT|INSERT|UPDATE|DELETE)\b.*?\$_(GET|POST|REQUEST|COOKIE)/i',
            'XSS'      => '/echo\s+(?!.*?(?:htmlspecialchars|e\(|urlencode|intval|strip_tags|filter_input)).*?\$_(GET|POST|REQUEST|COOKIE)/i',
            'RCE'      => '/(?<!->)\b(system|exec|shell_exec|passthru|popen|proc_open|eval)\s*\(/i',
            'LFI'      => '/(include|require)(_once)?\s*\(?\s*\$_(GET|POST|REQUEST|COOKIE)/i',
            'WeakHash' => '/\b(md5|sha1)\s*\(\s*\$[a-zA-Z_]*(password|pass|pwd|secret)/i',
        ];

        $findings = [];
        foreach ($files as $file) {
            $content = @file_get_contents($file); if (!$content) continue;
            $rel = substr($file, strlen($this->config->getTargetDir()) + 1);
            
            foreach (explode("\n", $content) as $lineNum => $line) {
                $trimmed = ltrim($line);
                if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) continue;
                
                foreach ($rules as $name => $regex) {
                    if (preg_match($regex, $line)) {
                        $snippet = trim(substr($line, 0, 120));
                        $findings[] = sprintf('[%s] %s:%d | %s', $name, $rel, $lineNum + 1, $snippet);
                    }
                }
            }
        }

        if (empty($findings)) {
            $this->addResult('PASS', 'No security issues detected.');
        } else {
            $this->addResult('FAIL', count($findings) . ' security issues detected.', ['list' => $findings]);
            FileSystem::writeLog($this->config->getLogDir(), 'security-issues', $findings);
        }
        return $this->results;
    }
}