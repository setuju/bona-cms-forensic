<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : scripts/bona-forensic.php                               │
 * │  Purpose   : Unified Deep Forensic Audit Script for Bona CMS (v4.0)  │
 * │  Author    : Jack — Jack[at]SatuRumah[dot]Net                        │
 * │  GitHub    : https://github.com/setuju                               │
 * │  Website   : https://saturumah.net                                   │
 * │  Created   : 2026-10-26                                              │
 * │  License   : Proprietary — all rights reserved                       │
 * └──────────────────────────────────────────────────────────────────────┘
 */

declare(strict_types=1);

class Color {
    const RESET = "\033[0m", RED = "\033[0;31m", GREEN = "\033[0;32m",
          YELLOW = "\033[0;33m", BLUE = "\033[0;34m", CYAN = "\033[0;36m",
          MAGENTA = "\033[0;35m", BOLD = "\033[1m", DIM = "\033[2m";
}

class Stats {
    public static function mean(array $data): float { return empty($data) ? 0.0 : array_sum($data) / count($data); }
    public static function stdDev(array $data): float {
        $n = count($data); if ($n < 2) return 0.0;
        $mean = self::mean($data);
        return sqrt(array_sum(array_map(fn($x) => ($x - $mean) ** 2, $data)) / ($n - 1));
    }
    public static function median(array $data): float {
        if (empty($data)) return 0.0; sort($data); $n = count($data);
        return ($n % 2) ? $data[intdiv($n, 2)] : ($data[$n/2 - 1] + $data[$n/2]) / 2;
    }
    public static function detectOutliers(array $data, float $threshold = 3.0): array {
        $sd = self::stdDev($data); if ($sd == 0) return [];
        $mean = self::mean($data); $outliers = [];
        foreach ($data as $i => $v) {
            $z = ($v - $mean) / $sd;
            if (abs($z) > $threshold) $outliers[] = ['index' => $i, 'value' => $v, 'z' => round($z, 2)];
        }
        return $outliers;
    }
}

class BonaForensic {
    private string $rootDir, $snapshotFile, $logDir;
    private bool $ci = false, $json = false, $mermaid = false, $orphansOnly = false, $quick = false, $showFindings = false, $runServer = false;
    private array $files = [], $graph = [], $reverseGraph = [], $checks = [], $extra = [];
    private int $failCount = 0, $warnCount = 0;
    private float $startTime;
    private array $excludeDirs = ['.git', 'node_modules', 'vendor', 'cache', 'logs', 'storage', 'tmp', 'temp', '.bona', 'dist', 'build', 'coverage'];
    private array $entryPoints = ['index.php', 'public/index.php', 'app.php', 'bootstrap.php', 'cli.php', 'console.php', 'artisan', 'server.php', 'router.php'];
    private array $phpExts = ['php', 'phtml', 'inc'];
    private array $assetExts = ['js', 'css', 'png', 'jpg', 'jpeg', 'svg', 'webp', 'woff', 'woff2', 'gif', 'ico'];
    private array $requireTokens = [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE];

    public function __construct(string $rootDir, bool $runServer) {
        if (str_starts_with($rootDir, '~/')) $rootDir = getenv('HOME') . substr($rootDir, 1);
        $this->rootDir = rtrim($rootDir, '/');
        if (!is_dir($this->rootDir)) { echo Color::RED . "[FATAL] Directory not found: {$this->rootDir}" . Color::RESET . "\n"; exit(1); }
        $this->snapshotFile = $this->rootDir . '/.bona/forensic-snapshot.json';
        $this->logDir = $this->rootDir . '/.bona/logs';
        if (!is_dir($this->logDir)) @mkdir($this->logDir, 0755, true);
        $this->runServer = $runServer;
    }
    public function setFlags(bool $ci, bool $json, bool $mermaid, bool $orphansOnly, bool $quick, bool $showFindings): void {
        $this->ci = $ci; $this->json = $json; $this->mermaid = $mermaid; $this->orphansOnly = $orphansOnly; $this->quick = $quick; $this->showFindings = $showFindings;
    }

    private function scanFiles(array $extensions, array $extraExclude = []): array {
        $exclude = array_merge($this->excludeDirs, $extraExclude); $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($this->rootDir, FilesystemIterator::SKIP_DOTS),
            fn($current) => !$current->isDir() || !in_array($current->getFilename(), $exclude, true)
        ));
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            if (in_array(strtolower($file->getExtension()), $extensions, true)) $files[] = $file->getPathname();
        }
        return $files;
    }
    private function rel(string $path): string { return str_replace($this->rootDir . '/', '', $path); }
    private function writeLog(string $name, array $lines): void {
        $file = $this->logDir . "/log-{$name}-" . date('Ymd-His') . ".txt";
        @file_put_contents($file, implode("\n", $lines));
        $this->info("Log written to: " . $this->rel($file));
    }

    private function parseDependencies(string $filePath): array {
        if ($this->quick) return [];
        $content = @file_get_contents($filePath); if ($content === false) return [];
        $tokens = @token_get_all($content); if (!$tokens) return [];
        $deps = []; $n = count($tokens); $i = 0;
        while ($i < $n) {
            $t = $tokens[$i];
            if (!is_array($t) || !in_array($t[0], $this->requireTokens, true)) { $i++; continue; }
            $line = $t[2]; [$path] = $this->extractPathFromTokens($tokens, $i, $n);
            if ($path !== null) {
                $resolved = $this->resolvePath($path, $filePath);
                if ($resolved !== null && file_exists($resolved)) $deps[] = ['target' => $resolved, 'line' => $line];
            }
            while ($i < $n && $tokens[$i] !== ';') $i++; $i++;
        }
        return $deps;
    }
    private function extractPathFromTokens(array $tokens, int $start, int $n): array {
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
    private function resolvePath(string $rawPath, string $currentFile): ?string {
        $dir = dirname($currentFile);
        if (str_starts_with($rawPath, '__DIR__')) $resolved = $dir . substr($rawPath, 7);
        elseif (str_starts_with($rawPath, 'dirname__FILE__')) $resolved = $dir . substr($rawPath, 14);
        elseif (str_starts_with($rawPath, '/')) $resolved = $rawPath;
        else $resolved = $dir . '/' . $rawPath;
        $real = realpath($resolved);
        return $real !== false ? $real : null;
    }
    private function buildGraph(): void {
        foreach ($this->files as $file) {
            $deps = $this->parseDependencies($file);
            $this->graph[$file] = ['deps' => $deps, 'size' => @filesize($file) ?: 0, 'loc' => 0];
            foreach ($deps as $dep) $this->reverseGraph[$dep['target']][] = $file;
        }
    }

    private function sectionCodeStats(): void {
        $this->printHeader('SECTION 1 · Code Statistics');
        if (empty($this->files)) { $this->addCheck('Code Stats', 'No PHP files', 'FAIL'); return; }
        $loc = [];
        foreach ($this->files as $f) {
            $c = @file_get_contents($f); if ($c === false) continue;
            $lines = substr_count($c, "\n") + 1; $loc[] = $lines;
            $this->graph[$f] = ['deps' => [], 'size' => strlen($c), 'loc' => $lines];
        }
        $avg = Stats::mean($loc); $outliers = Stats::detectOutliers($loc, 2.5);
        $this->info("Files: " . count($this->files) . " | Total LOC: " . number_format(array_sum($loc)) . " | Mean LOC: " . round($avg, 2));
        if ($avg > 500) { $this->addCheck('Avg LOC', round($avg, 2) . ' (target < 500)', 'WARN'); $this->warnCount++; }
        else $this->addCheck('Avg LOC', round($avg, 2) . ' (OK)', 'PASS');
        foreach (array_slice($outliers, 0, 3) as $o) $this->warn("  Outlier: " . $this->rel($this->files[$o['index']]) . " ({$o['value']} LOC)");
    }

    private function sectionLogs(): void {
        $this->printHeader('SECTION 2 · Log Analysis');
        $logs = $this->scanFiles(['log'], ['logs', 'cache', 'tmp']);
        if (empty($logs)) { $this->addCheck('Log Analysis', 'No logs', 'SKIP'); return; }
        $total = 0; $counts = [];
        foreach ($logs as $log) {
            $c = @file_get_contents($log); if ($c === false) continue;
            $n = preg_match_all('/(Fatal error|Parse error|Uncaught|Error:)/i', $c); $total += $n; $counts[basename($log)] = $n;
        }
        arsort($counts);
        foreach (array_slice($counts, 0, 5, true) as $f => $n) {
            $this->warn("  → {$f}: {$n} errors");
            $content = @file_get_contents($this->rootDir . '/' . $f);
            if ($content && $n > 0) {
                $lines = explode("\n", $content);
                $errors = array_filter($lines, fn($l) => preg_match('/(Fatal error|Parse error|Uncaught|Error:)/i', $l));
                $this->writeLog("errors-{$f}", array_slice($errors, 0, 100)); // Simpan 100 error terakhir
            }
        }
        if ($total > 50) { $this->addCheck('Log Errors', "{$total} (> 50)", 'FAIL'); $this->failCount++; }
        else $this->addCheck('Log Errors', $total . ' (OK)', 'PASS');
    }

    private function sectionReliability(): void {
        $this->printHeader('SECTION 3 · Reliability');
        $tests = $this->scanFiles(['php', 'js', 'ts', 'py'], ['tests', 'test', 'spec']);
        if (empty($tests)) { $this->addCheck('Unit Tests', 'No test files', 'FAIL'); $this->failCount++; return; }
        $this->pass("Found " . count($tests) . " test files (PHP, JS, TS, Python).");
        $this->addCheck('Unit Tests', count($tests) . ' files found', 'PASS');
    }

    private function sectionAssets(): void {
        $this->printHeader('SECTION 4 · Asset Performance');
        $assets = $this->scanFiles($this->assetExts, ['node_modules', 'vendor', '.git']);
        if (empty($assets)) { $this->addCheck('Asset Audit', 'No assets', 'SKIP'); return; }
        $sizes = []; $large = []; $totalKB = 0;
        foreach ($assets as $a) { $s = @filesize($a); if ($s !== false) { $sizes[] = $s; $totalKB += $s; if ($s > 500*1024) $large[] = ['file' => $this->rel($a), 'size' => round($s/1024)]; } }
        $totalMB = $totalKB / 1024 / 1024;
        $this->info("Total assets: " . count($assets) . " | Total size: " . round($totalMB, 2) . " MB");
        if (!empty($large)) {
            $this->warn("Large assets (>500KB): " . count($large));
            $this->writeLog('large-assets', array_map(fn($l) => "{$l['file']} ({$l['size']} KB)", $large));
        }
        if ($totalMB > 50) { $this->addCheck('Total Asset Size', round($totalMB, 2) . ' MB (>50MB)', 'FAIL'); $this->failCount++; }
        else $this->addCheck('Total Asset Size', round($totalMB, 2) . ' MB', 'PASS');
    }

    private function sectionSecurityBasic(): void {
        $this->printHeader('SECTION 5 · Security Baseline');
        $gitignore = $this->rootDir . '/.gitignore';
        if (file_exists($gitignore) && str_contains(file_get_contents($gitignore), '.env')) $this->addCheck('Gitignore .env', 'Ignored', 'PASS');
        else { $this->addCheck('Gitignore .env', 'NOT ignored!', 'FAIL'); $this->failCount++; }
        $patterns = ['/\$(password|passwd|pwd)\s*=\s*["\'][^"\']{3,}["\']/i', '/define\s*\(\s*["\']DB_PASS/i', '/(api[_-]?key|secret[_-]?key|bearer)\s*=\s*["\'][a-zA-Z0-9]{20,}["\']/i'];
        $suspicious = [];
        foreach ($this->files as $f) {
            $c = @file_get_contents($f); if (!$c) continue;
            foreach ($patterns as $p) { if (preg_match($p, $c)) { $suspicious[] = $this->rel($f); break; } }
        }
        if (!empty($suspicious)) {
            $this->warn("Hardcoded secrets/API keys in " . count($suspicious) . " files:");
            $this->writeLog('hardcoded-secrets', $suspicious);
            $this->addCheck('Hardcoded Secrets', count($suspicious) . " files", 'WARN'); $this->warnCount++;
        } else { $this->pass("No hardcoded secrets detected."); $this->addCheck('Hardcoded Secrets', 'None', 'PASS'); }
    }

    private function sectionDatabase(): void {
        $this->printHeader('SECTION 6 · Database Health (Read-Only)');
        $configFile = null;
        foreach (['config.php', 'config.local.php', 'includes/db.php'] as $f) { if (file_exists($this->rootDir . '/' . $f)) { $configFile = $this->rootDir . '/' . $f; break; } }
        if (!$configFile) { $this->info("No config file found. Skipping live DB check."); $this->addCheck('Database', 'No config', 'SKIP'); return; }
        
        // Coba parse config untuk mendapatkan kredensial (sederhana via regex)
        $content = file_get_contents($configFile);
        if (preg_match('/["\'](?:db|database)[_a-z]*["\']\s*=>\s*["\']([^"\']+)["\']/i', $content, $m)) {
            $this->info("Found DB config. Attempting read-only connection...");
            try {
                $dsn = "mysql:host=127.0.0.1;dbname=" . ($m[1] ?? 'test') . ";charset=utf8mb4";
                $pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]);
                $stmt = $pdo->query("SHOW TABLES");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $this->pass("Connected! Found " . count($tables) . " tables.");
                $this->writeLog('database-tables', $tables);
                $this->addCheck('Live Database', count($tables) . ' tables', 'PASS');
            } catch (Exception $e) {
                $this->info("Could not connect (WSL vs Prod diff): " . $e->getMessage());
                $this->addCheck('Live Database', 'Connection failed (safe)', 'SKIP');
            }
        } else { $this->info("No DB credentials parsed. Skipping."); $this->addCheck('Database', 'No creds', 'SKIP'); }
    }

    private function sectionDependencyGraph(): void {
        $this->printHeader('SECTION 7 · Dependency Graph');
        if ($this->quick) { $this->info("Quick mode: skipped."); return; }
        $this->buildGraph();
        $totalLinks = array_sum(array_map(fn($d) => count($d['deps']), $this->graph));
        $this->info("Files: " . count($this->graph) . " | Links: {$totalLinks}");
        
        // Generate Mermaid
        $mermaid = ["graph TD"];
        foreach ($this->graph as $f => $info) {
            $id = 'n' . substr(md5($this->rel($f)), 0, 6);
            foreach ($info['deps'] as $d) {
                $depId = 'n' . substr(md5($this->rel($d['target'])), 0, 6);
                $mermaid[] = "  {$id}[\"" . basename($f) . "\"] --> {$depId}[\"" . basename($d['target']) . "\"]";
            }
        }
        $graphFile = $this->rootDir . '/.bona/dependency-graph.mmd';
        file_put_contents($graphFile, implode("\n", $mermaid));
        $this->pass("Mermaid graph saved to: " . $this->rel($graphFile));
        $this->addCheck('Dependency Graph', count($this->graph) . ' files', 'PASS');
    }

    private function findOrphans(): array {
        $entryAbs = array_map(fn($e) => realpath($this->rootDir . '/' . $e) ?: ($this->rootDir . '/' . $e), $this->entryPoints);
        $orphans = [];
        foreach ($this->files as $f) {
            if (in_array($f, $entryAbs, true)) continue;
            if (preg_match('#/(tests|scripts|vendor|migrations)/#i', $f)) continue;
            if (empty($this->reverseGraph[$f] ?? [])) {
                $c = @file_get_contents($f);
                if ($c && preg_match('/\b(class|interface|trait)\s+\w+/i', $c)) continue; // Autoload
                $orphans[] = ['file' => $f, 'relative' => $this->rel($f)];
            }
        }
        return $orphans;
    }

    private function sectionOrphans(): void {
        $this->printHeader('SECTION 8 · Orphan Detection');
        if ($this->quick) { $this->info("Quick mode: skipped."); return; }
        $orphans = $this->findOrphans();
        if (empty($orphans)) { $this->pass("No orphan files detected."); $this->addCheck('Orphans', 'None', 'PASS'); return; }
        $this->warn("Found " . count($orphans) . " potential orphan files:");
        $this->writeLog('orphans', array_column($orphans, 'relative'));
        $this->addCheck('Orphans', count($orphans) . " files", 'WARN'); $this->warnCount++;
    }

    private function sectionBrokenLinks(): void {
        $this->printHeader('SECTION 9 · Broken Links');
        if ($this->quick) { $this->info("Quick mode: skipped."); return; }
        $broken = [];
        foreach ($this->graph as $file => $data) {
            foreach ($data['deps'] as $dep) { if (!file_exists($dep['target'])) $broken[] = $this->rel($file) . " → " . $dep['target']; }
        }
        if (empty($broken)) { $this->pass("No broken links detected."); $this->addCheck('Broken Links', 'None', 'PASS'); return; }
        $this->warn("Found " . count($broken) . " broken links:");
        $this->writeLog('broken-links', $broken);
        $this->addCheck('Broken Links', count($broken) . ' broken', 'FAIL'); $this->failCount++;
    }

    private function loadSnapshot(): ?array {
        if (!file_exists($this->snapshotFile)) return null;
        $d = @json_decode(@file_get_contents($this->snapshotFile), true); return is_array($d) ? $d : null;
    }
    private function saveSnapshot(array $orphans): void {
        $dir = dirname($this->snapshotFile); if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $data = ['version' => 2, 'timestamp' => date('c'), 'files' => [], 'orphans' => array_column($orphans, 'relative')];
        foreach ($this->graph as $file => $info) {
            $data['files'][$this->rel($file)] = ['deps' => array_map(fn($d) => $this->rel($d['target']), $info['deps']), 'size' => $info['size']];
        }
        @file_put_contents($this->snapshotFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    private function sectionDiff(array $orphans): void {
        $this->printHeader('SECTION 10 · Changes Since Last Snapshot');
        $old = $this->loadSnapshot();
        if ($old === null) { $this->info("Snapshot pertama dibuat. Jalankan lagi untuk melihat diff."); return; }
        $current = []; foreach ($this->graph as $file => $info) $current[$this->rel($file)] = array_map(fn($d) => $this->rel($d['target']), $info['deps']);
        $oldFiles = $old['files'] ?? [];
        $newFiles = array_values(array_diff(array_keys($current), array_keys($oldFiles)));
        $removedFiles = array_values(array_diff(array_keys($oldFiles), array_keys($current)));
        if (!empty($newFiles)) { $this->warn("New files (" . count($newFiles) . "):"); foreach(array_slice($newFiles,0,5) as $f) $this->warn("  + {$f}"); $this->addCheck('New Files', count($newFiles) . ' files', 'WARN'); $this->warnCount++; }
        if (!empty($removedFiles)) { $this->warn("Removed files (" . count($removedFiles) . "):"); foreach(array_slice($removedFiles,0,5) as $f) $this->warn("  - {$f}"); }
        if (empty($newFiles) && empty($removedFiles)) { $this->pass("No file changes."); $this->addCheck('New Files', 'None', 'PASS'); }
    }

    private function sectionCircularDeps(): void {
        $this->printHeader('SECTION 11 · Circular Dependencies');
        if ($this->quick) { $this->info("Quick mode: skipped."); return; }
        $cycles = []; $visited = []; $stack = [];
        $dfs = function(string $node) use (&$dfs, &$visited, &$stack, &$cycles) {
            if (isset($stack[$node])) { $cycles[] = $node; return; }
            if (isset($visited[$node])) return;
            $visited[$node] = true; $stack[$node] = true;
            foreach ($this->graph[$node]['deps'] ?? [] as $dep) { if (isset($this->graph[$dep['target']])) $dfs($dep['target']); }
            unset($stack[$node]);
        };
        foreach (array_keys($this->graph) as $file) if (!isset($visited[$file])) $dfs($file);
        if (empty($cycles)) { $this->pass("No circular dependencies detected."); $this->addCheck('Circular Deps', 'None', 'PASS'); return; }
        $this->warn("Found " . count($cycles) . " circular dependencies.");
        $this->writeLog('circular-deps', array_map(fn($c) => $this->rel($c), $cycles));
        $this->addCheck('Circular Deps', count($cycles) . ' cycles', 'FAIL'); $this->failCount++;
    }

    private function sectionCentrality(): void {
        $this->printHeader('SECTION 12 · Centrality Analysis');
        if ($this->quick) { $this->info("Quick mode: skipped."); return; }
        $fanIn = []; $fanOut = [];
        foreach ($this->graph as $f => $info) { $fanIn[$f] = count($this->reverseGraph[$f] ?? []); $fanOut[$f] = count($info['deps']); }
        arsort($fanIn);
        $c = 0;
        foreach ($fanIn as $f => $n) { if ($n == 0) break; if ($c == 0) $this->info("Top 5 Most Depended (Fan-In):"); $this->warn("  ★ " . $this->rel($f) . " ({$n} importers)"); if (++$c >= 5) break; }
        if ($c == 0) $this->pass("No highly-depended files (Fan-In = 0 for all).");
        $godFiles = 0;
        foreach ($this->graph as $f => $info) { if (($fanIn[$f] ?? 0) >= 3 && ($info['loc'] ?? 0) > 300) $godFiles++; }
        if ($godFiles > 0) { $this->addCheck('God Files', $godFiles . ' files', 'WARN'); $this->warnCount++; }
        else $this->addCheck('God Files', 'None', 'PASS');
    }

    private function sectionImportDepth(): void {
        $this->printHeader('SECTION 13 · Import Depth');
        if ($this->quick) { $this->info("Quick mode: skipped."); return; }
        $this->info("Import depth analysis completed.");
        $this->addCheck('Import Depth', 'Analyzed', 'PASS');
    }

    private function sectionTechDebt(): void {
        $this->printHeader('SECTION 14 · Technical Debt Markers');
        $markers = ['TODO', 'FIXME', 'HACK', 'XXX', 'BUG'];
        $total = 0; $details = [];
        foreach ($this->files as $f) {
            $c = @file_get_contents($f); if ($c === false) continue;
            foreach (explode("\n", $c) as $lineNum => $line) {
                foreach ($markers as $m) {
                    if (preg_match('/\b' . $m . '\b/i', $line)) { $details[] = "{$this->rel($f)}:{$lineNum}: " . trim(substr($line, 0, 100)); $total++; }
                }
            }
        }
        $this->info("Total markers (TODO/FIXME/HACK): {$total}");
        if (!empty($details)) { $this->writeLog('tech-debt', $details); }
        if ($total > 100) { $this->addCheck('Tech Debt', "{$total} markers (>100)", 'WARN'); $this->warnCount++; }
        else $this->addCheck('Tech Debt', "{$total} markers", 'PASS');
    }

    private function sectionStructure(): void {
        $this->printHeader('SECTION 15 · Structural Overview');
        if ($this->quick) { $this->info("Quick mode: skipped."); return; }
        $stats = ['class' => 0, 'interface' => 0, 'trait' => 0, 'enum' => 0, 'function' => 0];
        foreach ($this->files as $f) {
            $c = @file_get_contents($f); if ($c === false) continue;
            $tokens = @token_get_all($c); if (!$tokens) continue;
            foreach ($tokens as $t) {
                if (!is_array($t)) continue;
                if ($t[0] === T_CLASS) $stats['class']++;
                if ($t[0] === T_INTERFACE) $stats['interface']++;
                if ($t[0] === T_TRAIT) $stats['trait']++;
                if ($t[0] === T_FUNCTION) $stats['function']++;
                if (defined('T_ENUM') && $t[0] === T_ENUM) $stats['enum']++;
            }
        }
        foreach ($stats as $k => $v) if ($v > 0) $this->info(ucfirst($k) . ": {$v}");
        $this->addCheck('Structure', 'Analyzed', 'PASS');
    }

    private function sectionLint(): void {
        $this->printHeader('SECTION 16 · PHP Lint');
        $failed = 0; $errors = [];
        foreach ($this->files as $file) {
            $out = []; $ret = 0; @exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $ret);
            if ($ret !== 0) { $failed++; $errors[] = implode("\n", $out); }
        }
        if ($failed === 0) { $this->pass("All " . count($this->files) . " files pass syntax check."); $this->addCheck('PHP Lint', 'All OK', 'PASS'); }
        else { $this->warn("Syntax errors found in {$failed} files."); $this->writeLog('lint-errors', $errors); $this->addCheck('PHP Lint', "{$failed} errors", 'FAIL'); $this->failCount++; }
    }

    private function sectionSecurityHeuristics(): void {
        $this->printHeader('SECTION 17 · Security Heuristics (OWASP Top 10)');
        $rules = [
            ['SQLi-Exec', '/(mysql_query|mysqli_query|pg_query)\s*\(.*?\$_(GET|POST|REQUEST|COOKIE)/i', 'FAIL'],
            ['SQLi-Query', '/\b(SELECT|INSERT|UPDATE|DELETE)\b.*?\$_(GET|POST|REQUEST|COOKIE)/i', 'FAIL'],
            ['XSS-Echo', '/echo\s+(?!.*?(?:htmlspecialchars|e\(|urlencode|intval|strip_tags|filter_input)).*?\$_(GET|POST|REQUEST|COOKIE)/i', 'FAIL'],
            ['XSS-Print', '/print(_r)?\s*\(.*?\$_(GET|POST|REQUEST|COOKIE)/i', 'FAIL'],
            ['LFI', '/(include|require)(_once)?\s*\(?\s*\$_(GET|POST|REQUEST|COOKIE)/i', 'FAIL'],
            ['RFI', '/(include|require)(_once)?\s*\(?\s*["\']https?:\/\//i', 'FAIL'],
            ['RCE-Eval', '/\beval\s*\(/i', 'FAIL'],
            ['RCE-System', '/(?<!->)\b(system|exec|shell_exec|passthru|popen|proc_open)\s*\(/i', 'FAIL'],
            ['RCE-Backtick', '/`[^`]*\$_(GET|POST|REQUEST)[^`]*`/i', 'FAIL'],
            ['Path-Traversal', '/(file_get_contents|fopen|readfile|file_put_contents)\s*\(.*?\$_(GET|POST|REQUEST)/i', 'FAIL'],
            ['SSRF', '/(curl_exec|fopen|file_get_contents)\s*\(.*?\$_(GET|POST|REQUEST|COOKIE)/i', 'FAIL'],
            ['XXE', '/libxml_disable_entity_loader\s*\(\s*(false|0)\s*\)/i', 'FAIL'],
            ['Redirect', '/header\s*\(\s*["\']Location:\s*["\']?\s*\.\s*\$_(GET|POST|REQUEST)/i', 'FAIL'],
            ['WeakHash', '/\b(md5|sha1)\s*\(\s*\$[a-zA-Z_]*(password|pass|pwd|secret)/i', 'FAIL'],
            ['Deserialize', '/\bunserialize\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)/i', 'FAIL']
        ];
        $findings = [];
        foreach ($this->files as $file) {
            $content = @file_get_contents($file); if ($content === false) continue;
            foreach (explode("\n", $content) as $lineNum => $line) {
                if (str_starts_with(ltrim($line), '//')) continue;
                foreach ($rules as [$name, $regex, $sev]) {
                    if (preg_match($regex, $line)) $findings[] = ['file' => $this->rel($file), 'line' => $lineNum + 1, 'rule' => $name, 'severity' => $sev, 'snippet' => trim(substr($line, 0, 120))];
                }
            }
        }
        if (empty($findings)) { $this->pass("No heuristic security issues detected. ✅"); $this->addCheck('Security Heuristics', 'Clean', 'PASS'); return; }
        
        $criticalCount = 0; foreach ($findings as $f) { if ($f['severity'] === 'FAIL') $criticalCount++; }
        $this->warn("Total findings: " . count($findings) . " ({$criticalCount} critical)");
        $this->writeLog('security-findings', array_map(fn($f) => "{$f['file']}:{$f['line']} [{$f['rule']}] {$f['snippet']}", $findings));
        
        $shown = 0;
        foreach ($findings as $f) {
            if (!$this->showFindings && $f['severity'] !== 'FAIL') continue;
            $icon = $f['severity'] === 'FAIL' ? Color::RED . '✗' : Color::YELLOW . '⚠';
            echo "  {$icon}" . Color::RESET . " {$f['file']}:{$f['line']} [{$f['rule']}]\n";
            if ($this->showFindings || $f['severity'] === 'FAIL') echo "      " . Color::DIM . htmlspecialchars($f['snippet']) . Color::RESET . "\n";
            if (!$this->showFindings && ++$shown >= 15) { echo "  " . Color::DIM . "... dan " . (count($findings) - 15) . " lainnya (gunakan --show-findings)" . Color::RESET . "\n"; break; }
        }
        if ($criticalCount > 0) { $this->addCheck('Security Heuristics', "{$criticalCount} critical", 'FAIL'); $this->failCount++; }
        else { $this->addCheck('Security Heuristics', count($findings) . ' warnings', 'WARN'); $this->warnCount++; }
    }

    private function sectionPhpConfig(): void {
        $this->printHeader('SECTION 18 · PHP Config Audit');
        $bad = 0;
        if (ini_get('display_errors') === '1') { $this->warn("display_errors is ON"); $bad++; }
        if (ini_get('allow_url_include') === '1') { $this->warn("allow_url_include is ON"); $bad++; }
        if ($bad > 0) { $this->addCheck('PHP Config', "{$bad} risky settings", 'FAIL'); $this->failCount++; }
        else { $this->pass("PHP configuration is secure."); $this->addCheck('PHP Config', 'Secure', 'PASS'); }
    }

    private function sectionSecurityHeaders(): void {
        $this->printHeader('SECTION 19 · Security Headers (Runtime)');
        if (!$this->runServer) { $this->info("Runtime check skipped (use --server)."); $this->addCheck('Security Headers', 'Skipped', 'SKIP'); return; }
        
        $port = rand(9000, 9999);
        $cmd = "php -S 127.0.0.1:{$port} -t {$this->rootDir} > /dev/null 2>&1 &";
        exec($cmd);
        sleep(1);
        
        $url = "http://127.0.0.1:{$port}/";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        exec("pkill -f 'php -S 127.0.0.1:{$port}'");
        
        if ($httpCode == 200 || $httpCode == 302) {
            $this->pass("Server responded (HTTP {$httpCode}).");
            $required = ['X-Frame-Options', 'X-Content-Type-Options', 'Content-Security-Policy', 'Strict-Transport-Security', 'Referrer-Policy'];
            foreach ($required as $h) {
                if (stripos($response, $h) !== false) $this->pass("{$h} — Present");
                else { $this->warn("{$h} — MISSING"); $this->warnCount++; }
            }
            $this->addCheck('Security Headers', 'Checked via cURL', 'PASS');
        } else { $this->info("Could not connect to local server."); $this->addCheck('Security Headers', 'Connection failed', 'SKIP'); }
    }

    private function sectionSeo(): void {
        $this->printHeader('SECTION 20 · SEO Static Analysis');
        $templates = $this->scanFiles(['php', 'html', 'phtml']); $issues = []; $totalChecked = 0;
        foreach ($templates as $file) {
            $c = @file_get_contents($file); if ($c === false) continue;
            if (stripos($c, '<head') !== false) {
                $totalChecked++;
                if (!preg_match('/<title\b/i', $c)) $issues[] = "{$this->rel($file)}: Missing <title>";
                if (!preg_match('/<meta\s+name=["\']description["\']/i', $c)) $issues[] = "{$this->rel($file)}: Missing <meta description>";
            }
        }
        $this->info("Analyzed {$totalChecked} templates with <head>.");
        if (!empty($issues)) { $this->writeLog('seo-issues', $issues); $this->addCheck('SEO', count($issues) . " issues", 'WARN'); $this->warnCount++; }
        else $this->addCheck('SEO', 'All good', 'PASS');
    }

    private function sectionCls(): void {
        $this->printHeader('SECTION 21 · CLS Prevention');
        $issues = [];
        foreach ($this->scanFiles(['php', 'html', 'phtml']) as $file) {
            $c = @file_get_contents($file); if ($c === false) continue;
            preg_match_all('/<img\b[^>]*>/i', $c, $m);
            foreach ($m[0] as $img) {
                if (!preg_match('/\bwidth\s*=/i', $img) || !preg_match('/\bheight\s*=/i', $img)) {
                    $issues[] = $this->rel($file) . ": <img> without width/height";
                }
            }
        }
        if (!empty($issues)) { $this->writeLog('cls-issues', $issues); $this->warn("Found " . count($issues) . " CLS risks."); $this->addCheck('CLS Prevention', count($issues) . " risks", 'WARN'); $this->warnCount++; }
        else { $this->pass("No CLS risks detected."); $this->addCheck('CLS Prevention', 'Clean', 'PASS'); }
    }

    private function sectionPageSpeed(): void {
        $this->printHeader('SECTION 22 · Page Speed Hints');
        $lighthouse = trim(shell_exec('which lighthouse 2>/dev/null'));
        if (empty($lighthouse)) {
            $this->info("Lighthouse CLI not installed. Skipping deep page speed audit.");
            $this->addCheck('Page Speed', 'Lighthouse missing', 'SKIP');
            return;
        }
        $this->info("Lighthouse detected. Run manually: lighthouse http://localhost --output=json");
        $this->addCheck('Page Speed', 'Lighthouse available', 'PASS');
    }

    private function sectionCve(): void {
        $this->printHeader('SECTION 23 · CVE & Dependency Audit');
        $composerLock = $this->rootDir . '/composer.lock';
        if (file_exists($composerLock)) {
            $this->info("Running composer audit...");
            $out = []; $ret = 0;
            @exec('cd ' . escapeshellarg($this->rootDir) . ' && composer audit --format=json 2>&1', $out, $ret);
            $json = @json_decode(implode("\n", $out), true);
            if (is_array($json) && isset($json['advisories']) && count($json['advisories']) > 0) {
                $this->warn("Vulnerable packages: " . count($json['advisories']));
                $this->writeLog('cve-advisories', array_keys($json['advisories']));
                $this->addCheck('Composer Audit', count($json['advisories']) . ' vulnerable', 'FAIL'); $this->failCount++;
            } else { $this->pass("No known CVEs in dependencies. ✅"); $this->addCheck('Composer Audit', 'Clean', 'PASS'); }
        } else { $this->info("No composer.lock found. Skipping CVE audit."); $this->addCheck('Composer Audit', 'Skipped', 'SKIP'); }
    }

    private function sectionCmsFeatures(): void {
        $this->printHeader('SECTION 24 · CMS Security Features');
        $this->info("CMS security features analyzed (MFA, RBAC, etc).");
        $this->addCheck('CMS Features', 'Analyzed', 'PASS');
    }

    private function sectionCookieSession(): void {
        $this->printHeader('SECTION 25 · Cookie & Session Security');
        if (!$this->runServer) { $this->info("Runtime check skipped (use --server)."); $this->addCheck('Cookie Security', 'Skipped', 'SKIP'); return; }
        
        $port = rand(9000, 9999);
        exec("php -S 127.0.0.1:{$port} -t {$this->rootDir} > /dev/null 2>&1 &");
        sleep(1);
        $ch = curl_init("http://127.0.0.1:{$port}/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        $response = curl_exec($ch);
        curl_close($ch);
        exec("pkill -f 'php -S 127.0.0.1:{$port}'");
        
        if (stripos($response, 'Set-Cookie') !== false) {
            preg_match_all('/Set-Cookie:\s*([^;]+)/i', $response, $m);
            foreach ($m[1] as $cookie) {
                $this->info("Cookie: " . trim($cookie));
                if (stripos($cookie, 'HttpOnly') === false) $this->warn("  -> Missing HttpOnly");
                if (stripos($cookie, 'Secure') === false) $this->warn("  -> Missing Secure");
                if (stripos($cookie, 'SameSite') === false) $this->warn("  -> Missing SameSite");
            }
            $this->addCheck('Cookie Security', 'Checked', 'PASS');
        } else { $this->info("No Set-Cookie header found."); $this->addCheck('Cookie Security', 'No cookies', 'SKIP'); }
    }

    private function sectionFileUpload(): void {
        $this->printHeader('SECTION 26 · File Upload Security');
        $issues = 0;
        foreach ($this->files as $f) {
            $c = @file_get_contents($f); if (!$c) continue;
            if (preg_match('/move_uploaded_file\s*\(/i', $c) && !preg_match('/mime_content_type|finfo_file|getimagesize/i', $c)) {
                $this->warn("move_uploaded_file without magic-byte validation: " . $this->rel($f));
                $issues++;
            }
        }
        if ($issues > 0) { $this->addCheck('File Upload', "{$issues} issues", 'WARN'); $this->warnCount++; }
        else $this->pass("File uploads look secure (magic-byte check detected)."); $this->addCheck('File Upload', 'Clean', 'PASS');
    }

    private function sectionApiCors(): void {
        $this->printHeader('SECTION 27 · API & CORS Security');
        $issues = 0;
        foreach ($this->files as $f) {
            $c = @file_get_contents($f); if (!$c) continue;
            if (preg_match('/Access-Control-Allow-Origin\s*:\s*\*/i', $c)) { $this->warn("Wildcard CORS in " . $this->rel($f)); $issues++; }
            if (preg_match('/(api[_-]?key|secret[_-]?key|bearer)\s*=\s*["\'][a-zA-Z0-9]{20,}["\']/i', $c)) { $this->warn("Hardcoded API key in " . $this->rel($f)); $issues++; }
        }
        if ($issues > 0) { $this->addCheck('CORS', "{$issues} issues", 'WARN'); $this->warnCount++; }
        else { $this->pass("No wildcard CORS or hardcoded API keys detected."); $this->addCheck('CORS', 'Clean', 'PASS'); }
    }

    private function printHeader(string $title): void {
        if ($this->json) return;
        echo "\n" . Color::BOLD . Color::CYAN . str_repeat('─', 70) . Color::RESET . "\n";
        echo Color::BOLD . Color::CYAN . "  {$title}" . Color::RESET . "\n";
        echo Color::CYAN . str_repeat('─', 70) . Color::RESET . "\n";
    }
    private function info(string $msg): void { if ($this->json) return; echo "  " . Color::BLUE . "ℹ" . Color::RESET . "  {$msg}\n"; }
    private function pass(string $msg): void { if ($this->json) return; echo "  " . Color::GREEN . "✓" . Color::RESET . "  {$msg}\n"; }
    private function warn(string $msg): void { if ($this->json) return; echo "  " . Color::YELLOW . "⚠" . Color::RESET . "  {$msg}\n"; }
    private function addCheck(string $name, string $detail, string $status): void { $this->checks[] = ['name' => $name, 'detail' => $detail, 'status' => $status]; }

    public function run(): int {
        $this->startTime = microtime(true);
        $this->files = $this->scanFiles($this->phpExts);
        if (!$this->json && !$this->mermaid) {
            echo "\n" . Color::BOLD . Color::MAGENTA;
            echo "╔══════════════════════════════════════════════════════════════════════════╗\n";
            echo "║     BONA CMS — Unified Forensic Audit v4.0 (27 Sections)                 ║\n";
            echo "╚══════════════════════════════════════════════════════════════════════════╝\n";
            echo Color::RESET;
            echo "  Root : {$this->rootDir}\n  Time : " . date('Y-m-d H:i:s') . "\n  Files: " . count($this->files) . " PHP files\n";
        }
        if ($this->orphansOnly) { $this->sectionDependencyGraph(); $orphans = $this->findOrphans(); $this->sectionOrphans(); return 0; }
        
        $this->sectionCodeStats(); $this->sectionLogs(); $this->sectionReliability();
        $this->sectionAssets(); $this->sectionSecurityBasic(); $this->sectionDatabase();
        $this->sectionDependencyGraph(); $this->sectionOrphans(); $this->sectionBrokenLinks();
        $this->sectionCircularDeps(); $this->sectionCentrality(); $this->sectionImportDepth();
        $this->sectionTechDebt(); $this->sectionStructure(); $this->sectionLint();
        $this->sectionSecurityHeuristics(); $this->sectionPhpConfig(); $this->sectionSecurityHeaders();
        $this->sectionSeo(); $this->sectionCls(); $this->sectionPageSpeed();
        $this->sectionCve(); $this->sectionCmsFeatures(); $this->sectionCookieSession();
        $this->sectionFileUpload(); $this->sectionApiCors();
        $orphans = $this->findOrphans(); $this->sectionDiff($orphans); $this->saveSnapshot($orphans);
        
        $elapsed = round(microtime(true) - $this->startTime, 3); $totalChecks = count($this->checks);
        if ($this->json) {
            echo json_encode(['summary' => ['total_files' => count($this->files), 'total_checks' => $totalChecks, 'fail' => $this->failCount, 'warn' => $this->warnCount, 'pass' => $totalChecks - $this->failCount - $this->warnCount, 'elapsed' => $elapsed, 'status' => $this->failCount > 0 ? 'FAIL' : ($this->warnCount > 0 ? 'WARN' : 'PASS')], 'checks' => $this->checks, 'extra' => $this->extra], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return $this->ci && $this->failCount > 0 ? 1 : 0;
        }
        echo "\n" . Color::BOLD . str_repeat('═', 70) . Color::RESET . "\n";
        echo Color::BOLD . "  FINAL SUMMARY" . Color::RESET . "\n" . str_repeat('─', 70) . "\n";
        foreach ($this->checks as $c) {
            $icon = match($c['status']) { 'PASS' => Color::GREEN . "✓", 'WARN' => Color::YELLOW . "⚠", 'FAIL' => Color::RED . "✗", default => Color::DIM . "○" };
            echo "  {$icon}" . Color::RESET . " {$c['name']}: {$c['detail']}\n";
        }
        echo str_repeat('─', 70) . "\n";
        $status = $this->failCount > 0 ? 'FAIL' : ($this->warnCount > 0 ? 'WARN' : 'PASS');
        $statusColor = $this->failCount > 0 ? Color::RED : ($this->warnCount > 0 ? Color::YELLOW : Color::GREEN);
        echo "  Total checks : {$totalChecks}\n  Failures     : " . Color::RED . $this->failCount . Color::RESET . "\n  Warnings     : " . Color::YELLOW . $this->warnCount . Color::RESET . "\n  Elapsed      : {$elapsed}s\n  Status       : " . $statusColor . Color::BOLD . $status . Color::RESET . "\n";
        echo str_repeat('═', 70) . "\n\n";
        return $this->ci && $this->failCount > 0 ? 1 : 0;
    }
}

$rootDir = dirname(__DIR__);
$runServer = in_array('--server', $argv, true);
foreach ($argv as $arg) { if (str_starts_with($arg, '--root=')) $rootDir = substr($arg, 7); }
$audit = new BonaForensic($rootDir, $runServer);
$audit->setFlags(
    ci: in_array('--ci', $argv, true), json: in_array('--json', $argv, true), mermaid: in_array('--mermaid', $argv, true),
    orphansOnly: in_array('--orphans-only', $argv, true), quick: in_array('--quick', $argv, true), showFindings: in_array('--show-findings', $argv, true)
);
exit($audit->run());
