<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Checkers/SecurityChecker.php
 * │  Purpose   : OWASP Top 10 heuristics: SQLi, XSS, RCE, LFI, CSRF
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

        $findings = []; $selectStar = []; $unprepared = [];
        
        foreach ($files as $file) {
            $content = @file_get_contents($file); if (!$content) continue;
            $rel = substr($file, strlen($this->config->getTargetDir()) + 1);
            
            foreach (explode("\n", $content) as $lineNum => $line) {
                $trimmed = ltrim($line);
                if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) continue;
                if (preg_match('/\$_(GET|POST|REQUEST)\[.*?\]\s*(?:===|==|!==|!=)\s*["\']/i', $line)) continue;
                
                foreach ($rules as $name => $regex) {
                    if (preg_match($regex, $line)) {
                        $snippet = trim(substr($line, 0, 120));
                        $findings[] = sprintf('[%s] %s:%d | %s', $name, $rel, $lineNum + 1, $snippet);
                    }
                }
                
                if (preg_match('/\bSELECT\s+\*\s+FROM\b/i', $line)) {
                    $selectStar[] = sprintf('%s:%d | %s', $rel, $lineNum + 1, trim(substr($line, 0, 100)));
                }
                
                if (preg_match('/->query\s*\(.*?["\'].*?\$[a-zA-Z_]/i', $line) && !preg_match('/prepare|bindParam|bindValue/i', $line)) {
                    $unprepared[] = sprintf('%s:%d | %s', $rel, $lineNum + 1, trim(substr($line, 0, 100)));
                }
            }
        }

        if (!empty($findings)) $this->addResult('FAIL', count($findings) . ' security issues detected.', ['list' => $findings]);
        else $this->addResult('PASS', 'No critical security issues (XSS/SQLi/RCE/LFI).');
        
        if (!empty($selectStar)) $this->addResult('WARN', count($selectStar) . ' queries use SELECT * (data over-fetching).', ['list' => $selectStar]);
        else $this->addResult('PASS', 'No SELECT * queries detected.');
        
        if (!empty($unprepared)) $this->addResult('FAIL', count($unprepared) . ' queries WITHOUT prepared statements (SQLi risk).', ['list' => $unprepared]);
        else $this->addResult('PASS', 'All queries use prepared statements.');
        
        if (!empty($findings) || !empty($unprepared)) {
            FileSystem::writeLog($this->config->getLogDir(), 'security-issues', array_merge($findings, $unprepared));
        }
        return $this->results;
    }
}