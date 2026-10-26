<?php
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