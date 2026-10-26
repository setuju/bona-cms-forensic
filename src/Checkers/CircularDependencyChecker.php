<?php
declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

use SatuRumah\Forensic\Core\FileSystem;

class CircularDependencyChecker extends AbstractChecker {
    public function getName(): string { return 'Circular Dependencies'; }

    public function run(): array {
        $files = FileSystem::scanDir($this->config->getTargetDir(), ['php'], ['vendor', 'node_modules', '.git']);
        $requireTokens = [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE];
        $graph = [];
        $dc = new DependencyChecker($this->config);

        foreach ($files as $file) {
            $content = @file_get_contents($file); if ($content === false) continue;
            $tokens = @token_get_all($content); if (!$tokens) continue;
            $rel = substr($file, strlen($this->config->getTargetDir()) + 1);
            $graph[$rel] = [];
            
            $i = 0; $n = count($tokens);
            while ($i < $n) {
                $t = $tokens[$i];
                if (!is_array($t) || !in_array($t[0], $requireTokens, true)) { $i++; continue; }
                [$path] = $dc->extractPath($tokens, $i, $n);
                if ($path !== null) {
                    $resolved = $dc->resolvePath($path, $file);
                    if ($resolved !== null && file_exists($resolved)) {
                        $graph[$rel][] = substr($resolved, strlen($this->config->getTargetDir()) + 1);
                    }
                }
                while ($i < $n && $tokens[$i] !== ';') $i++; $i++;
            }
        }

        $cycles = []; $visited = []; $stack = [];
        $dfs = function(string $node) use (&$dfs, &$visited, &$stack, &$cycles, $graph) {
            if (isset($stack[$node])) { $cycles[] = $node; return; }
            if (isset($visited[$node])) return;
            $visited[$node] = true; $stack[$node] = true;
            foreach ($graph[$node] ?? [] as $dep) { if (isset($graph[$dep])) $dfs($dep); }
            unset($stack[$node]);
        };
        foreach (array_keys($graph) as $file) if (!isset($visited[$file])) $dfs($file);

        if (empty($cycles)) { $this->addResult('PASS', 'No circular dependencies detected.'); }
        else { $this->addResult('FAIL', count($cycles) . ' circular dependencies found.', ['list' => $cycles]); }
        
        return $this->results;
    }
}