<?php
declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

use SatuRumah\Forensic\Core\FileSystem;

class LogChecker extends AbstractChecker {
    public function getName(): string { return 'Log Analysis'; }
    public function run(): array {
        $logs = FileSystem::scanDir($this->config->getTargetDir(), ['log'], ['logs', 'cache', 'tmp', 'vendor']);
        if (empty($logs)) { $this->addResult('SKIP', 'No log files found.'); return $this->results; }
        $total = 0; $counts = [];
        foreach ($logs as $log) {
            $c = @file_get_contents($log); if ($c === false) continue;
            $n = preg_match_all('/(Fatal error|Parse error|Uncaught|Error:)/i', $c); 
            $total += $n; 
            $counts[basename($log)] = $n;
        }
        arsort($counts);
        foreach (array_slice($counts, 0, 5, true) as $f => $n) {
            $this->addResult('INFO', "{$f}: {$n} errors");
        }
        if ($total > 50) { $this->addResult('FAIL', "Total {$total} errors (> 50)"); }
        else { $this->addResult('PASS', "Total {$total} errors"); }
        return $this->results;
    }
}