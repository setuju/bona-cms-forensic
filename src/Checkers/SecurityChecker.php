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
            // Skip file audit internal (DatabaseChecker memang query metadata DB)
            $isInternalAuditFile = (basename($file) === 'DatabaseChecker.php');
            if ($isInternalAuditFile) continue;
            
            // v2.2.5: match() whitelist detection
            $matchVars = [];
            if (preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*match\s*\(/', $content, $mm)) {
                foreach ($mm[1] as $mv) $matchVars[$mv] = true;
            }

            foreach (explode("\n", $content) as $lineNum => $line) {
                $trimmed = ltrim($line);
                if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) continue;
                if (preg_match('/\$_(GET|POST|REQUEST)\[.*?\]\s*(?:===|==|!==|!=)\s*["\']/i', $line)) continue;
                
                foreach ($rules as $name => $regex) {
                    if ($name === 'RCE' && (preg_match('/escapeshellarg\s*\(/', $content) || preg_match('/\b(which\s|php\s+-S|php\s+-l|composer\s+|semgrep\s+|pkill\s)/i', $line))) {
                        continue;
                    }
                    if (preg_match($regex, $line)) {
                        $snippet = trim(substr($line, 0, 120));
                        $findings[] = sprintf('[%s] %s:%d | %s', $name, $rel, $lineNum + 1, $snippet);
                    }
                }
                
                if (preg_match('/\bSELECT\s+\*\s+FROM\b/i', $line)) {
                    $selectStar[] = sprintf('%s:%d | %s', $rel, $lineNum + 1, trim(substr($line, 0, 100)));
                }
                
                $isIntrospection = preg_match('/\b(SHOW\s+(TABLES|COLUMNS|INDEX|KEYS|DATABASES)|PRAGMA\s+\w+|DESCRIBE\s+\w+|EXPLAIN\s+)/i', $line);
                if (!$isIntrospection && preg_match('/->query\s*\(.*?["\'].*?\$([a-zA-Z_][a-zA-Z0-9_]*)/i', $line, $qm) && !preg_match('/prepare|bindParam|bindValue/i', $line)) {
                    if (!isset($matchVars[$qm[1]])) {
                        $unprepared[] = sprintf('%s:%d | %s', $rel, $lineNum + 1, trim(substr($line, 0, 100)));
                    }
                }
            }
        }

        if (!empty($findings)) $this->addResult('FAIL', count($findings) . ' security issues detected.', ['list' => $findings]);
        else $this->addResult('PASS', 'No critical security issues (XSS/SQLi/RCE/LFI).');
        
        if (!empty($selectStar)) $this->addResult('WARN', count($selectStar) . ' queries use SELECT * (data over-fetching).', ['list' => $selectStar]);
        else $this->addResult('PASS', 'No SELECT * queries detected.');
        
        if (!empty($unprepared)) $this->addResult('FAIL', count($unprepared) . ' queries WITHOUT prepared statements (SQLi risk).', ['list' => $unprepared]);
        else $this->addResult('PASS', 'All queries use prepared statements.');
        
        // v2.5.0: CSRF surface check (multi-method: file-level + form-block)
        $csrfFileIssues = [];
        $csrfFormIssues = [];
        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content === false) continue;
            if (!preg_match('/<form\b[^>]*\bmethod\s*=\s*["\']POST["\']/i', $content)) continue;
            $rel = substr($file, strlen($this->config->getTargetDir()) + 1);

            // Method 1 — file-level: any csrf handling keyword in file?
            $fileHasCsrf = preg_match('/\bcsrf_field\s*\(|\bcsrf_token\b|\bcsrf_validate\b|name\s*=\s*["\']_token["\']|\bcsrfmiddlewaretoken\b/i', $content);
            if (!$fileHasCsrf) {
                $csrfFileIssues[] = $rel;
                continue;
            }

            // Method 2 — form-block level: each POST form body references csrf or _token
            if (preg_match_all('/<form\b[^>]*\bmethod\s*=\s*["\']POST["\'][^>]*>(.*?)<\/form>/is', $content, $forms)) {
                foreach ($forms[1] as $idx => $body) {
                    if (!preg_match('/csrf|_token/i', $body)) {
                        $csrfFormIssues[] = $rel . ' (form #' . ($idx + 1) . ')';
                    }
                }
            }
        }

        if (!empty($csrfFileIssues)) {
            $this->addResult('WARN', count($csrfFileIssues) . ' file(s) with POST form but no CSRF token (file-level).', ['list' => array_slice($csrfFileIssues, 0, 20)]);
        } else {
            $this->addResult('PASS', 'All POST-form files contain CSRF token handling.');
        }
        if (!empty($csrfFormIssues)) {
            $this->addResult('WARN', count($csrfFormIssues) . ' form(s) without CSRF token in body.', ['list' => array_slice($csrfFormIssues, 0, 20)]);
        }

        if (!empty($findings) || !empty($unprepared) || !empty($csrfFileIssues) || !empty($csrfFormIssues)) {
            FileSystem::writeLog($this->config->getLogDir(), 'security-issues', array_merge($findings, $unprepared, $csrfFileIssues, $csrfFormIssues));
        }
        return $this->results;
    }
}