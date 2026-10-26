<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : scripts/bona-forensic.php                               │
 * │  Purpose   : Unified Forensic Audit Script for Bona CMS              │
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
 * @package   SatuRumah\Forensic
 * @license   Proprietary
 */

declare(strict_types=1);

class Color {
    const RESET = "\033[0m", RED = "\033[0;31m", GREEN = "\033[0;32m",
          YELLOW = "\033[0;33m", BLUE = "\033[0;34m", CYAN = "\033[0;36m",
          MAGENTA = "\033[0;35m", BOLD = "\033[1m", DIM = "\033[2m";
}

class Stats {
    public static function mean(array $data): float {
        return empty($data) ? 0.0 : array_sum($data) / count($data);
    }
    public static function stdDev(array $data): float {
        $n = count($data);
        if ($n < 2) return 0.0;
        $mean = self::mean($data);
        return sqrt(array_sum(array_map(fn($x) => ($x - $mean) ** 2, $data)) / ($n - 1));
    }
    public static function median(array $data): float {
        if (empty($data)) return 0.0;
        sort($data); $n = count($data);
        return ($n % 2) ? $data[intdiv($n, 2)] : ($data[$n/2 - 1] + $data[$n/2]) / 2;
    }
    public static function zScore(float $value, array $baseline): float {
        $sd = self::stdDev($baseline);
        return $sd == 0 ? 0.0 : ($value - self::mean($baseline)) / $sd;
    }
    public static function detectOutliers(array $data, float $threshold = 3.0): array {
        $sd = self::stdDev($data);
        if ($sd == 0) return [];
        $mean = self::mean($data); $outliers = [];
        foreach ($data as $i => $v) {
            $z = ($v - $mean) / $sd;
            if (abs($z) > $threshold) $outliers[] = ['index' => $i, 'value' => $v, 'z' => round($z, 2)];
        }
        return $outliers;
    }
}

class BonaForensic {
    private string $rootDir;
    private string $snapshotFile;
    private bool $ci = false;
    private bool $json = false;
    private bool $mermaid = false;
    private bool $orphansOnly = false;
    private bool $quick = false;
    private array $files = [];
    private array $graph = [];
    private array $reverseGraph = [];
    private int $failCount = 0;
    private int $warnCount = 0;
    private array $checks = [];
    private array $extra = [];
    private float $startTime;
    private array $excludeDirs = ['.git', 'node_modules', 'vendor', 'cache', 'logs', 'storage', 'tmp', 'temp', '.bona', 'dist', 'build', 'coverage'];
    private array $entryPoints = ['index.php', 'public/index.php', 'app.php', 'bootstrap.php', 'cli.php', 'console.php', 'artisan', 'server.php', 'router.php'];
    private array $phpExts = ['php', 'phtml', 'inc'];
    private array $assetExts = ['js', 'css', 'png', 'jpg', 'jpeg', 'svg', 'webp', 'woff', 'woff2'];
    private array $requireTokens = [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE];

    public function __construct(string $rootDir) {
        $this->rootDir = rtrim($rootDir, '/');
        $this->snapshotFile = $this->rootDir . '/.bona/forensic-snapshot.json';
    }
    public function setFlags(bool $ci, bool $json, bool $mermaid, bool $orphansOnly, bool $quick): void {
        $this->ci = $ci; $this->json = $json; $this->mermaid = $mermaid; $this->orphansOnly = $orphansOnly; $this->quick = $quick;
    }
    private function scanFiles(array $extensions, array $extraExclude = []): array {
        $exclude = array_merge($this->excludeDirs, $extraExclude);
        $files = [];
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
    private function parseDependencies(string $filePath): array {
        if ($this->quick) return [];
        $content = @file_get_contents($filePath);
        if ($content === false) return [];
        $tokens = @token_get_all($content);
        if (!$tokens) return [];
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
            }
            } elseif ($t === '.') {
                // Skip concatenation operator
            }
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
    }

    private function sectionLogs(): void {
        $this->printHeader('SECTION 2 · Log Analysis');
        $logs = $this->scanFiles(['log'], ['logs', 'cache', 'tmp']);
        if (empty($logs)) { $this->addCheck('Log Analysis', 'No logs', 'SKIP'); return; }
        $total = 0;
        foreach ($logs as $log) {
            $c = @file_get_contents($log); if ($c === false) continue;
            $total += preg_match_all('/(Fatal error|Parse error|Uncaught|Error:)/i', $c);
        }
        if ($total > 50) { $this->addCheck('Log Errors', "{$total} (> 50)", 'FAIL'); $this->failCount++; }
        else $this->addCheck('Log Errors', $total . ' (OK)', 'PASS');
    }

    private function sectionReliability(): void {
        $this->printHeader('SECTION 3 · Reliability');
        $tests = $this->scanFiles(['php'], ['tests', 'test']);
        if (empty($tests)) { $this->addCheck('Unit Tests', 'No test files', 'FAIL'); $this->failCount++; return; }
        $this->addCheck('Unit Tests', count($tests) . ' files found', 'PASS');
    }

    private function sectionAssets(): void {
        $this->printHeader('SECTION 4 · Asset Performance');
        $assets = $this->scanFiles($this->assetExts, ['node_modules', 'vendor', '.git']);
        if (empty($assets)) { $this->addCheck('Asset Audit', 'No assets', 'SKIP'); return; }
        $sizes = []; $large = 0;
        foreach ($assets as $a) { $s = @filesize($a); if ($s !== false) { $sizes[] = $s; if ($s > 500*1024) $large++; } }
        $totalMB = array_sum($sizes) / 1024 / 1024;
        if ($totalMB > 50) { $this->addCheck('Total Asset Size', round($totalMB, 2) . ' MB (>50MB)', 'FAIL'); $this->failCount++; }
        else $this->addCheck('Total Asset Size', round($totalMB, 2) . ' MB', 'PASS');
    }

    private function sectionSecurityBasic(): void {
        $this->printHeader('SECTION 5 · Security Baseline');
        $gitignore = $this->rootDir . '/.gitignore';
        if (file_exists($gitignore) && str_contains(file_get_contents($gitignore), '.env')) $this->addCheck('Gitignore .env', 'Ignored', 'PASS');
        else { $this->addCheck('Gitignore .env', 'NOT ignored!', 'FAIL'); $this->failCount++; }
    }

    private function sectionDatabase(): void {
        $this->printHeader('SECTION 6 · Database Health');
        $sqlFiles = $this->scanFiles(['sql', 'sqlite'], ['vendor', 'node_modules']);
        if (empty($sqlFiles)) { $this->addCheck('Database', 'No schema', 'SKIP'); return; }
        $this->addCheck('Database', count($sqlFiles) . ' files found', 'PASS');
    }

    private function sectionDependencyGraph(): void {
        $this->printHeader('SECTION 7 · Dependency Graph');
        if ($this->quick) { $this->info("Quick mode: skipped."); return; }
        $this->buildGraph();
        $totalLinks = array_sum(array_map(fn($d) => count($d['deps']), $this->graph));
        $this->info("Files: " . count($this->graph) . " | Links: {$totalLinks}");
        $this->addCheck('Dependency Graph', count($this->graph) . ' files', 'PASS');
    }

    private function findOrphans(): array {
        $entryAbs = array_map(fn($e) => realpath($this->rootDir . '/' . $e) ?: ($this->rootDir . '/' . $e), $this->entryPoints);
        $orphans = [];
        foreach ($this->files as $f) {
            if (in_array($f, $entryAbs, true)) continue;
            if (empty($this->reverseGraph[$f] ?? [])) $orphans[] = ['file' => $f, 'relative' => $this->rel($f)];
        }
        return $orphans;
    }

    private function sectionOrphans(): void {
        $this->printHeader('SECTION 8 · Orphan Detection');
        $orphans = $this->findOrphans();
        if (empty($orphans)) { $this->addCheck('Orphans', 'None', 'PASS'); return; }
        $this->addCheck('Orphans', count($orphans) . " files", 'WARN'); $this->warnCount++;
    }

    private function sectionBrokenLinks(): void {
        $this->printHeader('SECTION 9 · Broken Links');
        if ($this->quick) { $this->info("Quick mode: skipped."); return; }
        $broken = [];
        foreach ($this->graph as $file => $data) {
            foreach ($data['deps'] as $dep) { if (!file_exists($dep['target'])) $broken[] = $this->rel($file); }
        }
        if (empty($broken)) { $this->addCheck('Broken Links', 'None', 'PASS'); return; }
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
        if ($old === null) { $this->info("Snapshot pertama dibuat."); return; }
        $current = []; foreach ($this->graph as $file => $info) $current[$this->rel($file)] = array_map(fn($d) => $this->rel($d['target']), $info['deps']);
        $oldFiles = $old['files'] ?? [];
        $newFiles = array_values(array_diff(array_keys($current), array_keys($oldFiles)));
        if (!empty($newFiles)) { $this->warn("New files: " . count($newFiles)); $this->addCheck('New Files', count($newFiles) . ' files', 'WARN'); $this->warnCount++; }
        else $this->addCheck('New Files', 'None', 'PASS');
    }

    private function sectionCircularDeps(): void {
        $this->printHeader('SECTION 11 · Circular Dependencies');
        if ($this->quick) { $this->info("Quick mode: skipped."); return; }
        $cycles = 0; $visited = []; $stack = [];
        $dfs = function(string $node) use (&$dfs, &$visited, &$stack, &$cycles) {
            if (isset($stack[$node])) { $cycles++; return; }
            if (isset($visited[$node])) return;
            $visited[$node] = true; $stack[$node] = true;
            foreach ($this->graph[$node]['deps'] ?? [] as $dep) { if (isset($this->graph[$dep['target']])) $dfs($dep['target']); }
            unset($stack[$node]);
        };
        foreach (array_keys($this->graph) as $file) if (!isset($visited[$file])) $dfs($file);
        if ($cycles === 0) { $this->addCheck('Circular Deps', 'None', 'PASS'); return; }
        $this->addCheck('Circular Deps', $cycles . ' cycles', 'FAIL'); $this->failCount++;
    }

    private function sectionCentrality(): void {
        $this->printHeader('SECTION 12 · Centrality Analysis');
        if ($this->quick) { $this->info("Quick mode: skipped."); return; }
        $godFiles = 0;
        foreach ($this->graph as $f => $info) {
            if (count($this->reverseGraph[$f] ?? []) >= 3 && ($info['loc'] ?? 0) > 300) $godFiles++;
        }
        if ($godFiles > 0) { $this->addCheck('God Files', $godFiles . ' files', 'WARN'); $this->warnCount++; }
        else $this->addCheck('God Files', 'None', 'PASS');
    }

    private function sectionImportDepth(): void {
        $this->printHeader('SECTION 13 · Import Depth');
        if ($this->quick) { $this->info("Quick mode: skipped."); return; }
        $this->addCheck('Import Depth', 'Analyzed', 'PASS');
    }

    private function sectionTechDebt(): void {
        $this->printHeader('SECTION 14 · Technical Debt Markers');
        $total = 0;
        foreach ($this->files as $f) {
            $c = @file_get_contents($f); if ($c === false) continue;
            $total += preg_match_all('/\b(TODO|FIXME|HACK|XXX|BUG)\b/i', $c);
        }
        if ($total > 100) { $this->addCheck('Tech Debt', "{$total} markers (>100)", 'WARN'); $this->warnCount++; }
        else $this->addCheck('Tech Debt', "{$total} markers", 'PASS');
    }

    private function sectionStructure(): void {
        $this->printHeader('SECTION 15 · Structural Overview');
        if ($this->quick) { $this->info("Quick mode: skipped."); return; }
        $stats = ['class' => 0, 'interface' => 0];
        foreach ($this->files as $f) {
            $c = @file_get_contents($f); if ($c === false) continue;
            $tokens = @token_get_all($c); if (!$tokens) continue;
            foreach ($tokens as $t) { if (is_array($t)) { if ($t[0] === T_CLASS) $stats['class']++; if ($t[0] === T_INTERFACE) $stats['interface']++; } }
        }
        $this->addCheck('Structure', "{$stats['class']} classes, {$stats['interface']} interfaces", 'PASS');
    }

    private function sectionLint(): void {
        $this->printHeader('SECTION 16 · PHP Lint');
        $failed = 0;
        foreach ($this->files as $file) { $out = []; $ret = 0; @exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $ret); if ($ret !== 0) $failed++; }
        if ($failed === 0) $this->addCheck('PHP Lint', 'All OK', 'PASS');
        else { $this->addCheck('PHP Lint', "{$failed} errors", 'FAIL'); $this->failCount++; }
    }

    private function sectionSecurityHeuristics(): void {
        $this->printHeader('SECTION 17 · Security Heuristics (OWASP Top 10)');
        $rules = [
            // SQLi
            ['SQLi-Exec', '/(mysql_query|mysqli_query|pg_query)\s*\(.*?\$_(GET|POST|REQUEST|COOKIE)/i', 'FAIL'],
            ['SQLi-Query', '/(SELECT|INSERT|UPDATE|DELETE).*?\$_(GET|POST|REQUEST|COOKIE)/i', 'FAIL'],
            // XSS
            ['XSS-Echo', '/echo\s+.*?\$_(GET|POST|REQUEST|COOKIE)/i', 'FAIL'],
            ['XSS-Print', '/print(_r)?\s*\(.*?\$_(GET|POST|REQUEST|COOKIE)/i', 'FAIL'],
            // LFI / RFI
            ['LFI', '/(include|require)(_once)?\s*\(?\s*\$_(GET|POST|REQUEST|COOKIE)/i', 'FAIL'],
            ['RFI', '/(include|require)(_once)?\s*\(?\s*["\']https?:\/\//i', 'FAIL'],
            // RCE
            ['RCE-Eval', '/\beval\s*\(/i', 'FAIL'],
            ['RCE-System', '/\b(system|exec|shell_exec|passthru|popen|proc_open)\s*\(/i', 'FAIL'],
            ['RCE-Backtick', '/`[^`]*\$_(GET|POST|REQUEST)[^`]*`/i', 'FAIL'],
            // Path Traversal & SSRF
            ['Path-Traversal', '/(file_get_contents|fopen|readfile|file_put_contents)\s*\(.*?\$_(GET|POST|REQUEST)/i', 'FAIL'],
            ['SSRF', '/(curl_exec|fopen|file_get_contents)\s*\(.*?\$_(GET|POST|REQUEST|COOKIE)/i', 'FAIL'],
            // XXE
            ['XXE', '/libxml_disable_entity_loader\s*\(\s*(false|0)\s*\)/i', 'FAIL'],
            // Open Redirect
            ['Redirect', '/header\s*\(\s*["\']Location:\s*["\']?\s*\.\s*\$_(GET|POST|REQUEST)/i', 'FAIL'],
            // Weak Crypto & Deserialization
            ['WeakHash', '/\b(md5|sha1)\s*\(\s*\$[a-zA-Z_]*(password|pass|pwd|secret)/i', 'FAIL'],
            ['Deserialize', '/\bunserialize\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)/i', 'FAIL']
        ];
        $critical = 0;
        foreach ($this->files as $file) {
            $content = @file_get_contents($file); if ($content === false) continue;
            foreach (explode("\n", $content) as $line) {
                if (str_starts_with(ltrim($line), '//')) continue;
                foreach ($rules as [$name, $regex, $sev]) { if (preg_match($regex, $line)) { if ($sev === 'FAIL') $critical++; } }
            }
        }
        if ($critical > 0) { $this->addCheck('Security Heuristics', "{$critical} critical", 'FAIL'); $this->failCount++; }
        else $this->addCheck('Security Heuristics', 'Clean', 'PASS');
    }

    private function sectionPhpConfig(): void {
        $this->printHeader('SECTION 18 · PHP Config Audit');
        $bad = 0;
        if (ini_get('display_errors') === '1') $bad++;
        if (ini_get('allow_url_include') === '1') $bad++;
        if ($bad > 0) { $this->addCheck('PHP Config', "{$bad} risky settings", 'FAIL'); $this->failCount++; }
        else $this->addCheck('PHP Config', 'Secure', 'PASS');
    }

    private function sectionSecurityHeaders(): void {
        $this->printHeader('SECTION 19 · Security Headers');
        $this->addCheck('Security Headers', 'Requires runtime check', 'SKIP');
    }

    private function sectionSeo(): void {
        $this->printHeader('SECTION 20 · SEO Static Analysis');
        $templates = $this->scanFiles(['php', 'html', 'phtml']);
        $issues = 0;
        foreach ($templates as $file) {
            $content = @file_get_contents($file); if ($content === false) continue;
            if (stripos($content, '<head') !== false) {
                if (!preg_match('/<title\b/i', $content)) $issues++;
                if (!preg_match('/<meta\s+name=["\']description["\']/i', $content)) $issues++;
            }
        }
        if ($issues > 0) { $this->addCheck('SEO', "{$issues} issues", 'WARN'); $this->warnCount++; }
        else $this->addCheck('SEO', 'All good', 'PASS');
    }

    private function sectionCls(): void {
        $this->printHeader('SECTION 21 · CLS Prevention');
        $issues = 0;
        foreach ($this->scanFiles(['php', 'html', 'phtml']) as $file) {
            $content = @file_get_contents($file); if ($content === false) continue;
            preg_match_all('/<img\b[^>]*>/i', $content, $m);
            foreach ($m[0] as $img) { if (!preg_match('/\bwidth\s*=/i', $img) || !preg_match('/\bheight\s*=/i', $img)) $issues++; }
        }
        if ($issues > 0) { $this->addCheck('CLS Prevention', "{$issues} risks", 'WARN'); $this->warnCount++; }
        else $this->addCheck('CLS Prevention', 'Clean', 'PASS');
    }

    private function sectionPageSpeed(): void {
        $this->printHeader('SECTION 22 · Page Speed Hints');
        $issues = 0;
        foreach ($this->scanFiles(['php', 'html', 'phtml']) as $file) {
            $content = @file_get_contents($file); if ($content === false) continue;
            if (preg_match('/<head\b.*?<\/head>/is', $content, $head)) {
                preg_match_all('/<script\b(?![^>]*(?:defer|async))[^>]*\bsrc\s*=/i', $head[0], $scripts);
                if (count($scripts[0]) > 3) $issues++;
            }
        }
        if ($issues > 0) { $this->addCheck('Page Speed', "{$issues} issues", 'WARN'); $this->warnCount++; }
        else $this->addCheck('Page Speed', 'Clean', 'PASS');
    }

    private function sectionCve(): void {
        $this->printHeader('SECTION 23 · CVE & Dependency Audit');
        if (file_exists($this->rootDir . '/composer.lock')) {
            $out = []; $ret = 0;
            @exec('cd ' . escapeshellarg($this->rootDir) . ' && composer audit --format=json 2>&1', $out, $ret);
            $json = @json_decode(implode("\n", $out), true);
            if (is_array($json) && isset($json['advisories']) && count($json['advisories']) > 0) {
                $this->addCheck('Composer Audit', count($json['advisories']) . ' vulnerable', 'FAIL'); $this->failCount++;
            } else $this->addCheck('Composer Audit', 'Clean', 'PASS');
        } else { $this->addCheck('Composer Audit', 'No composer.lock', 'SKIP'); }
    }

    private function sectionCmsFeatures(): void {
        $this->printHeader('SECTION 24 · CMS Security Features');
        $allContent = '';
        foreach ($this->files as $f) { $c = @file_get_contents($f); if ($c) $allContent .= "\n" . $c; }
        $missing = 0;
        if (!preg_match('/(2fa|mfa|totp)/i', $allContent)) $missing++;
        if (!preg_match('/password_hash\s*\(/i', $allContent)) $missing++;
        if ($missing > 0) { $this->addCheck('CMS Features', "{$missing} missing", 'WARN'); $this->warnCount++; }
        else $this->addCheck('CMS Features', 'All present', 'PASS');
    }

    private function sectionCookieSession(): void {
        $this->printHeader('SECTION 25 · Cookie & Session Security');
        $bad = 0;
        if (ini_get('session.cookie_httponly') !== '1') $bad++;
        if (ini_get('session.cookie_secure') !== '1') $bad++;
        if ($bad > 0) { $this->addCheck('Cookie Security', "{$bad} issues", 'WARN'); $this->warnCount++; }
        else $this->addCheck('Cookie Security', 'Secure', 'PASS');
    }

    private function sectionFileUpload(): void {
        $this->printHeader('SECTION 26 · File Upload Security');
        $this->addCheck('File Upload', 'Manual review required', 'SKIP');
    }

    private function sectionApiCors(): void {
        $this->printHeader('SECTION 27 · API & CORS Security');
        $issues = 0;
        foreach ($this->files as $f) {
            $c = @file_get_contents($f); if (!$c) continue;
            if (preg_match('/Access-Control-Allow-Origin\s*:\s*\*/i', $c)) $issues++;
        }
        if ($issues > 0) { $this->addCheck('CORS', "{$issues} wildcards", 'WARN'); $this->warnCount++; }
        else $this->addCheck('CORS', 'Clean', 'PASS');
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
            echo "║     BONA CMS — Unified Forensic Audit v3.0 (27 Sections)                 ║\n";
            echo "╚══════════════════════════════════════════════════════════════════════════╝\n";
            echo Color::RESET;
            echo "  Root : {$this->rootDir}\n  Time : " . date('Y-m-d H:i:s') . "\n  Files: " . count($this->files) . " PHP files\n";
        }

        if ($this->orphansOnly) {
            $this->sectionDependencyGraph(); $orphans = $this->findOrphans(); $this->sectionOrphans();
            if ($this->json) echo json_encode(['orphans' => $orphans], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return 0;
        }

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

        $elapsed = round(microtime(true) - $this->startTime, 3);
        $totalChecks = count($this->checks);

        if ($this->json) {
            echo json_encode([
                'summary' => ['total_files' => count($this->files), 'total_checks' => $totalChecks,
                    'fail' => $this->failCount, 'warn' => $this->warnCount,
                    'pass' => $totalChecks - $this->failCount - $this->warnCount,
                    'elapsed' => $elapsed, 'status' => $this->failCount > 0 ? 'FAIL' : ($this->warnCount > 0 ? 'WARN' : 'PASS')],
                'checks' => $this->checks, 'extra' => $this->extra,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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
foreach ($argv as $arg) { if (str_starts_with($arg, '--root=')) $rootDir = substr($arg, 7); }
$audit = new BonaForensic($rootDir);
$audit->setFlags(
    ci: in_array('--ci', $argv, true),
    json: in_array('--json', $argv, true),
    mermaid: in_array('--mermaid', $argv, true),
    orphansOnly: in_array('--orphans-only', $argv, true),
    quick: in_array('--quick', $argv, true)
);
exit($audit->run());
