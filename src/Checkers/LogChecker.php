<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Checkers/LogChecker.php
 * │  Purpose   : Application log error analyzer and anomaly detector
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

class LogChecker extends AbstractChecker {
    public function getName(): string { return 'Log Analysis'; }
    public function run(): array {
        $logs = FileSystem::scanDir($this->config->getTargetDir(), ['log'], ['logs', 'cache', 'tmp', 'vendor']);
        if (empty($logs)) { $this->addResult('SKIP', 'No log files found.'); return $this->results; }
        $total = 0;
        /** @var array<string, array{count:int, type:string, location:string, message:string}> $sigs */
        $sigs = [];
        foreach ($logs as $log) {
            $c = @file_get_contents($log); if ($c === false) continue;
            foreach (explode("\n", $c) as $line) {
                if (!preg_match('/(Fatal error|Parse error|Uncaught|Error:)/i', $line, $tm)) continue;
                $total++;
                $type = strtolower(trim($tm[1]));
                $location = '';
                if (preg_match('/\bin\s+(\/[^:]+):(\d+)/', $line, $lm)) $location = basename($lm[1]) . ':' . $lm[2];
                $message = '';
                if (preg_match('/:\s+(.+?)\s+in\s+\//', $line, $mm)) $message = substr(trim($mm[1]), 0, 80);
                $sig = $type . '|' . $location . '|' . $message;
                if (!isset($sigs[$sig])) $sigs[$sig] = ['count' => 0, 'type' => $type, 'location' => $location, 'message' => $message];
                $sigs[$sig]['count']++;
            }
        }
        uasort($sigs, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);
        foreach (array_slice($sigs, 0, 5, true) as $info) {
            $locPart = $info['location'] !== '' ? ' @ ' . $info['location'] : '';
            $msgPart = $info['message'] !== '' ? ' — ' . $info['message'] : '';
            $this->addResult('INFO', $info['type'] . $locPart . ' × ' . $info['count'] . $msgPart);
        }
        $unique = count($sigs);
        if ($total === 0) $this->addResult('PASS', 'No critical errors found in logs.');
        elseif ($total > 50) $this->addResult('FAIL', "Total {$total} errors across {$unique} unique signature(s) (> 50)");
        else $this->addResult('PASS', "Total {$total} errors across {$unique} unique signature(s)");
        return $this->results;
    }
}