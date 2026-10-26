<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Checkers/StatsChecker.php
 * │  Purpose   : Code statistics analyzer (LOC, mean, outliers)
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

class StatsChecker extends AbstractChecker {
    public function getName(): string { return 'Code Statistics'; }

    public function run(): array {
        $files = FileSystem::scanDir($this->config->getTargetDir(), ['php']);
        if (empty($files)) {
            $this->addResult('FAIL', 'No PHP files found');
            return $this->results;
        }

        $loc = [];
        foreach ($files as $f) {
            $c = @file_get_contents($f);
            if ($c !== false) $loc[] = substr_count($c, "\n") + 1;
        }

        $totalLoc = array_sum($loc);
        $meanLoc = count($loc) > 0 ? round($totalLoc / count($loc), 2) : 0;
        
        $this->addResult('PASS', "Files: " . count($files) . " | Total LOC: " . number_format($totalLoc) . " | Mean LOC: {$meanLoc}");
        return $this->results;
    }
}