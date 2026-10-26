<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Checkers/DependencyChecker.php
 * │  Purpose   : File dependency graph mapper with Mermaid output
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

class DependencyChecker extends AbstractChecker {
    private array $graph = [];
    private array $excludeDirs = ['vendor', 'node_modules', '.git', 'tests', 'storage', 'cache', 'tmp', '.bona'];

    public function getName(): string { return 'Dependency Graph'; }

    public function run(): array {
        $files = FileSystem::scanDir($this->config->getTargetDir(), ['php'], $this->excludeDirs);
        $requireTokens = [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE];

        foreach ($files as $file) {
            $content = @file_get_contents($file); if ($content === false) continue;
            $tokens = @token_get_all($content); if (!$tokens) continue;
            
            $deps = []; $n = count($tokens); $i = 0;
            while ($i < $n) {
                $t = $tokens[$i];
                if (!is_array($t) || !in_array($t[0], $requireTokens, true)) { $i++; continue; }
                [$path] = $this->extractPath($tokens, $i, $n);
                if ($path !== null) {
                    $resolved = $this->resolvePath($path, $file);
                    if ($resolved !== null && file_exists($resolved)) $deps[] = $resolved;
                }
                while ($i < $n && $tokens[$i] !== ';') $i++; $i++;
            }
            $rel = substr($file, strlen($this->config->getTargetDir()) + 1);
            $depsRel = array_map(fn($d) => substr($d, strlen($this->config->getTargetDir()) + 1), $deps);
            $this->graph[$rel] = $depsRel;
        }

        $totalLinks = array_sum(array_map('count', $this->graph));
        $this->addResult('PASS', "Analyzed " . count($files) . " files with {$totalLinks} links.");
        
        $this->generateMacroMermaid();
        return $this->results;
    }

    private function generateMacroMermaid(): void {
        $lines = ["graph TD"];
        $folderLinks = [];

        foreach ($this->graph as $file => $deps) {
            $fromFolder = dirname($file) === '.' ? 'root' : dirname($file);
            $fromFolder = str_replace('/', '_', $fromFolder);
            
            foreach ($deps as $dep) {
                $toFolder = dirname($dep) === '.' ? 'root' : dirname($dep);
                $toFolder = str_replace('/', '_', $toFolder);
                
                if ($fromFolder !== $toFolder) {
                    $folderLinks[$fromFolder][$toFolder] = true;
                }
            }
        }

        $allFolders = [];
        foreach ($folderLinks as $from => $tos) {
            $allFolders[$from] = true;
            foreach ($tos as $to => $val) $allFolders[$to] = true;
        }

        foreach (array_keys($allFolders) as $folder) {
            $safeName = str_replace('_', '/', $folder);
            $lines[] = sprintf('  %s["%s"]', $folder, $safeName);
        }

        foreach ($folderLinks as $from => $tos) {
            foreach ($tos as $to => $val) {
                $lines[] = sprintf('  %s --> %s', $from, $to);
            }
        }

        $mermaidStr = implode("\n", $lines);
        $filepath = $this->config->getToolDir() . '/.bona/dependency-graph-macro.mmd';
        @file_put_contents($filepath, $mermaidStr);
        $this->addResult('PASS', "Macro Mermaid graph saved (Folder-to-Folder).", ['file' => $filepath]);
    }

    public function extractPath(array $tokens, int $start, int $n): array {
        $i = $start + 1; $parts = [];
        while ($i < $n && $tokens[$i] === '(') $i++;
        while ($i < $n) {
            $t = $tokens[$i]; if ($t === ';' || $t === ')') break;
            if (is_array($t)) {
                switch ($t[0]) {
                    case T_CONSTANT_ENCAPSED_STRING: $parts[] = substr($t[1], 1, -1); break;
                    case T_DIR: $parts[] = '__DIR__'; break;
                    case T_FILE: $parts[] = '__FILE__'; break;
                    case T_STRING: if ($t[1] === 'dirname') $parts[] = 'dirname'; else return [null]; break;
                    case T_WHITESPACE: break;
                    default: return [null];
                }
            } elseif ($t === '.') { } else { return [null]; }
            $i++;
        }
        return empty($parts) ? [null] : [implode('', $parts)];
    }

    public function resolvePath(string $rawPath, string $currentFile): ?string {
        $dir = dirname($currentFile);
        if (str_starts_with($rawPath, '__DIR__')) $resolved = $dir . substr($rawPath, 7);
        elseif (str_starts_with($rawPath, 'dirname__FILE__')) $resolved = $dir . substr($rawPath, 14);
        elseif (str_starts_with($rawPath, '/')) $resolved = $rawPath;
        else $resolved = $dir . '/' . $rawPath;
        $real = realpath($resolved);
        return $real !== false ? $real : null;
    }
}