<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Checkers/BrokenLinksChecker.php
 * │  Purpose   : Detect require/include targets that don't exist on disk
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

class BrokenLinksChecker extends AbstractChecker {
    /** @var array<int, string> */
    private array $excludeDirs = ['vendor', 'node_modules', '.git', 'tests', 'storage', 'cache', 'tmp', '.bona'];

    public function getName(): string { return 'Broken Links'; }

    public function run(): array {
        $files = FileSystem::scanDir($this->config->getTargetDir(), ['php'], $this->excludeDirs);
        $requireTokens = [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE];
        /** @var array<int, array{file: string, line: int, target: string}> $broken */
        $broken = [];
        $checked = 0;

        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content === false) continue;
            $tokens = @token_get_all($content);
            if (!$tokens) continue;

            $n = count($tokens);
            $i = 0;
            while ($i < $n) {
                $t = $tokens[$i];
                if (!is_array($t) || !in_array($t[0], $requireTokens, true)) { $i++; continue; }
                $line = $t[2];
                [$path] = $this->extractPath($tokens, $i, $n);
                if ($path !== null) {
                    $checked++;
                    $resolved = $this->resolvePath($path, $file);
                    if ($resolved === null) {
                        $rel = substr($file, strlen($this->config->getTargetDir()) + 1);
                        $broken[] = ['file' => $rel, 'line' => $line, 'target' => $path];
                    }
                }
                while ($i < $n && $tokens[$i] !== ';') $i++;
                $i++;
            }
        }

        if (empty($broken)) {
            $this->addResult('PASS', "No broken require/include targets ({$checked} checked).");
            return $this->results;
        }

        $list = [];
        foreach (array_slice($broken, 0, 20) as $b) {
            $list[] = "{$b['file']}:{$b['line']} → {$b['target']}";
        }
        if (count($broken) > 20) {
            $list[] = '... and ' . (count($broken) - 20) . ' more';
        }
        $this->addResult('FAIL', count($broken) . ' broken require/include target(s)', ['list' => $list]);
        return $this->results;
    }

    /** @return array{0: ?string} */
    private function extractPath(array $tokens, int $start, int $n): array {
        $i = $start + 1;
        $parts = [];
        while ($i < $n && $tokens[$i] === '(') $i++;
        while ($i < $n) {
            $t = $tokens[$i];
            if ($t === ';' || $t === ')') break;
            if (is_array($t)) {
                switch ($t[0]) {
                    case T_CONSTANT_ENCAPSED_STRING: $parts[] = substr($t[1], 1, -1); break;
                    case T_DIR: $parts[] = '__DIR__'; break;
                    case T_FILE: $parts[] = '__FILE__'; break;
                    case T_STRING: if ($t[1] === 'dirname') $parts[] = 'dirname'; else return [null]; break;
                    case T_WHITESPACE: break;
                    default: return [null];
                }
            } elseif ($t === '.') {
                // concat operator — continue accumulating
            } else {
                return [null];
            }
            $i++;
        }
        return empty($parts) ? [null] : [implode('', $parts)];
    }

    private function resolvePath(string $rawPath, string $currentFile): ?string {
        $dir = dirname($currentFile);
        if (str_starts_with($rawPath, '__DIR__')) {
            $resolved = $dir . substr($rawPath, 7);
        } elseif (str_starts_with($rawPath, 'dirname__FILE__')) {
            $resolved = $dir . substr($rawPath, 14);
        } elseif (str_starts_with($rawPath, '/')) {
            $resolved = $rawPath;
        } else {
            $resolved = $dir . '/' . $rawPath;
        }
        $real = realpath($resolved);
        return $real !== false ? $real : null;
    }
}
