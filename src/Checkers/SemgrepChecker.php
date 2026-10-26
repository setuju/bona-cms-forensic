<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Checkers/SemgrepChecker.php
 * │  Purpose   : Semgrep SAST integration with custom YAML rules
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

class SemgrepChecker extends AbstractChecker {
    public function getName(): string { return 'Semgrep SAST (Custom Rules)'; }

    public function run(): array {
        $semgrep = trim(shell_exec('which semgrep 2>/dev/null') ?: '');
        if (empty($semgrep)) {
            $this->addResult('SKIP', 'Semgrep tidak terinstall. Jalankan: pip install semgrep');
            return $this->results;
        }

        $rulesDir = $this->config->getToolDir() . '/rules';
        if (!is_dir($rulesDir)) {
            $this->addResult('SKIP', 'Folder rules/ tidak ditemukan di tool directory.');
            return $this->results;
        }

        $targetDir = escapeshellarg($this->config->getTargetDir());
        $cmd = 'cd ' . escapeshellarg($this->config->getTargetDir()) . ' && semgrep --config=' . escapeshellarg($rulesDir) . ' --json --quiet . 2>/dev/null';
        $output = shell_exec($cmd);
        $json = @json_decode($output ?: '', true);

        if (!is_array($json) || !isset($json['results'])) {
            $this->addResult('SKIP', 'Semgrep gagal dijalankan atau output tidak valid.');
            return $this->results;
        }

        $results = $json['results'];
        $errorCount = 0; $warningCount = 0; $findings = [];

        foreach ($results as $r) {
            $severity = $r['extra']['severity'] ?? 'WARNING';
            $file = str_replace($this->config->getTargetDir() . '/', '', $r['path'] ?? '');
            $line = $r['start']['line'] ?? 0;
            $message = $r['extra']['message'] ?? 'No message';
            $ruleId = $r['check_id'] ?? 'unknown';
            $findings[] = sprintf('[%s] %s:%d | %s', $ruleId, $file, $line, $message);
            if ($severity === 'ERROR') $errorCount++;
            else $warningCount++;
        }

        $this->addResult('INFO', 'Semgrep findings: ' . count($results) . " (ERROR: {$errorCount}, WARNING: {$warningCount})");
        if ($errorCount > 0) {
            $this->addResult('FAIL', "{$errorCount} ERROR-level issues found by Semgrep.", ['list' => $findings]);
        } elseif ($warningCount > 0) {
            $this->addResult('WARN', "{$warningCount} WARNING-level issues found by Semgrep.", ['list' => $findings]);
        } else {
            $this->addResult('PASS', 'No issues found by Semgrep custom rules.');
        }
        return $this->results;
    }
}