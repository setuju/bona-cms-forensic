<?php
declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

use SatuRumah\Forensic\Core\FileSystem;

class ImportDepthChecker extends AbstractChecker {
    public function getName(): string { return 'Import Depth (Dependency Chain)'; }
    public function run(): array {
        $files = FileSystem::scanDir($this->config->getTargetDir(), ['php'], ['vendor', 'node_modules', '.git', 'tests', 'storage', 'cache', 'tmp', '.bona']);
        $requireTokens = [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE];
        $dc = new DependencyChecker($this->config);
        
        $graph = [];
        foreach ($files as $file) {
            $c = @file_get_contents($file); if (!$c) continue;
            $tokens = @token_get_all($c); if (!$tokens) continue;
            $rel = substr($file, strlen($this->config->getTargetDir()) + 1);
            $graph[$rel] = [];
            
            $i = 0; $n = count($tokens);
            while ($i < $n) {
                $t = $tokens[$i];
                if (!is_array($t) || !in_array($t[0], $requireTokens, true)) { $i++; continue; }
                [$path] = $dc->extractPath($tokens, $i, $n);
                if ($path !== null) {
                    $resolved = $dc->resolvePath($path, $file);
                    if ($resolved !== null && file_exists($resolved)) $graph[$rel][] = substr($resolved, strlen($this->config->getTargetDir()) + 1);
                }
                while ($i < $n && $tokens[$i] !== ';') $i++; $i++;
            }
        }

        // Cari entry points (file yang tidak di-require siapapun)
        $imported = [];
        foreach ($graph as $deps) foreach ($deps as $d) $imported[$d] = true;
        $entryPoints = array_filter(array_keys($graph), fn($f) => !isset($imported[$f]) && preg_match('/(?:^|\/)index\.php$|(?:^|\/)router.*\.php$/', $f));

        if (empty($entryPoints)) {
            $this->addResult('SKIP', 'No clear entry points found for depth analysis.');
            return $this->results;
        }

        $maxDepth = 0; $deepestFile = ''; $depthMap = [];
        foreach ($entryPoints as $entry) {
            $queue = [[$entry, 0]];
            $visited = [$entry => 0];
            while (!empty($queue)) {
                [$current, $depth] = array_shift($queue);
                if ($depth > $maxDepth) { $maxDepth = $depth; $deepestFile = $current; }
                if ($depth > ($depthMap[$current] ?? 0)) $depthMap[$current] = $depth;
                foreach ($graph[$current] ?? [] as $dep) {
                    if (!isset($visited[$dep]) || $visited[$dep] > $depth + 1) {
                        $visited[$dep] = $depth + 1;
                        $queue[] = [$dep, $depth + 1];
                    }
                }
            }
        }

        arsort($depthMap);
        $deepestFiles = [];
        foreach (array_slice($depthMap, 0, 10, true) as $file => $depth) {
            $deepestFiles[] = "Depth {$depth}: {$file}";
        }

        $this->addResult('INFO', "Analyzed " . count($graph) . " files from " . count($entryPoints) . " entry points.");
        $this->addResult('INFO', "Max Import Depth: {$maxDepth} (file: {$deepestFile})");
        if ($maxDepth > 10) $this->addResult('WARN', "Depth > 10 (too deep, hard to maintain).", ['list' => $deepestFiles]);
        else $this->addResult('PASS', "Depth OK (max: {$maxDepth}).", ['list' => $deepestFiles]);

        return $this->results;
    }
}