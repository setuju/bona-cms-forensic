<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Checkers/CentralityChecker.php
 * │  Purpose   : Code centrality and God File detection analyzer
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

class CentralityChecker extends AbstractChecker {
    public function getName(): string { return 'Centrality & God Files'; }

    public function run(): array {
        $files = FileSystem::scanDir($this->config->getTargetDir(), ['php'], ['vendor', 'node_modules', '.git', 'tests', 'storage', 'cache', 'tmp', '.bona']);
        $requireTokens = [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE];
        $dc = new DependencyChecker($this->config);
        
        $fanIn = []; $fanOut = []; $loc = [];
        foreach ($files as $file) {
            $c = @file_get_contents($file); if (!$c) continue;
            $tokens = @token_get_all($c); if (!$tokens) continue;
            $rel = substr($file, strlen($this->config->getTargetDir()) + 1);
            $fanOut[$rel] = 0;
            $loc[$rel] = substr_count($c, "\n") + 1;
            $fanIn[$rel] = $fanIn[$rel] ?? 0;
            
            $i = 0; $n = count($tokens);
            while ($i < $n) {
                $t = $tokens[$i];
                if (!is_array($t) || !in_array($t[0], $requireTokens, true)) { $i++; continue; }
                [$path] = $dc->extractPath($tokens, $i, $n);
                if ($path !== null) {
                    $resolved = $dc->resolvePath($path, $file);
                    if ($resolved !== null && file_exists($resolved)) {
                        $depRel = substr($resolved, strlen($this->config->getTargetDir()) + 1);
                        $fanOut[$rel]++;
                        $fanIn[$depRel] = ($fanIn[$depRel] ?? 0) + 1;
                    }
                }
                while ($i < $n && $tokens[$i] !== ';') $i++; $i++;
            }
        }

        arsort($fanIn);
        $topFanIn = [];
        foreach (array_slice($fanIn, 0, 10, true) as $f => $n) {
            if ($n === 0) break;
            $topFanIn[] = "{$n} importers | {$f}";
        }
        if (!empty($topFanIn)) $this->addResult('INFO', 'Top 10 Most Depended (Fan-In):', ['list' => $topFanIn]);

        $godFiles = [];
        foreach ($fanIn as $f => $n) {
            if ($n >= 5 && ($loc[$f] ?? 0) > 500) {
                $godFiles[] = "{$f} ({$n} importers, {$loc[$f]} LOC)";
            }
        }
        if (!empty($godFiles)) {
            $this->addResult('WARN', count($godFiles) . ' God Files detected (high fan-in + high LOC):', ['list' => $godFiles]);
        } else {
            $this->addResult('PASS', 'No God Files detected.');
        }
        return $this->results;
    }
}