<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Checkers/MaintenanceChecker.php
 * │  Purpose   : PHP lint, technical debt, and structural analyzer
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

class MaintenanceChecker extends AbstractChecker {
    public function getName(): string { return 'Maintenance & Code Quality'; }

    public function run(): array {
        $files = FileSystem::scanDir($this->config->getTargetDir(), ['php'], ['vendor', 'node_modules', '.git']);
        
        // 1. PHP Lint
        $failed = 0; $errors = [];
        foreach ($files as $file) {
            $out = []; $ret = 0;
            @exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $ret);
            if ($ret !== 0) { $failed++; $rel = substr($file, strlen($this->config->getTargetDir()) + 1); $errors[] = "{$rel} | " . implode(" ", $out); }
        }
        if ($failed === 0) { $this->addResult('PASS', "All " . count($files) . " files pass syntax check."); }
        else { $this->addResult('FAIL', "{$failed} files with syntax errors.", ['list' => $errors]); }

        // 2. Tech Debt (Hanya TODO, FIXME, HACK, BUG)
        $markers = ['TODO', 'FIXME', 'HACK', 'BUG'];
        $details = [];
        foreach ($files as $f) {
            $c = @file_get_contents($f); if ($c === false) continue;
            $rel = substr($f, strlen($this->config->getTargetDir()) + 1);
            // Skip self-source dan file yang berisi array marker (false positive)
            $isSelfSource = (basename($f) === 'MaintenanceChecker.php') || preg_match("/\['TODO'/", $c);
            if ($isSelfSource) continue;
            foreach (explode("\n", $c) as $lineNum => $line) {
                foreach ($markers as $m) {
                    // Gunakan word boundary dan pastikan bukan bagian dari string statis yang umum
                    if (preg_match('/\b' . $m . '\b/i', $line)) {
                        $snippet = trim(substr($line, 0, 100));
                        $details[] = sprintf('[%s] %s:%d | %s', $m, $rel, $lineNum + 1, $snippet);
                        break;
                    }
                }
            }
        }
        if (!empty($details)) {
            $this->addResult('WARN', count($details) . " technical debt markers found.", ['list' => $details]);
            FileSystem::writeLog($this->config->getLogDir(), 'tech-debt', $details);
        } else { $this->addResult('PASS', 'No technical debt markers.'); }

        // 3. Structure
        $stats = ['class' => 0, 'interface' => 0, 'trait' => 0, 'function' => 0];
        foreach ($files as $f) {
            $c = @file_get_contents($f); if ($c === false) continue;
            $tokens = @token_get_all($c); if (!$tokens) continue;
            foreach ($tokens as $t) {
                if (!is_array($t)) continue;
                if ($t[0] === T_CLASS) $stats['class']++;
                if ($t[0] === T_INTERFACE) $stats['interface']++;
                if ($t[0] === T_TRAIT) $stats['trait']++;
                if ($t[0] === T_FUNCTION) $stats['function']++;
            }
        }
        $this->addResult('INFO', "Structure: {$stats['class']} classes, {$stats['interface']} interfaces, {$stats['trait']} traits, {$stats['function']} functions.");

        return $this->results;
    }
}