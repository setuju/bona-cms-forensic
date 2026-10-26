<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Checkers/PageSpeedChecker.php
 * │  Purpose   : Detect page speed anti-patterns in HTML templates
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

class PageSpeedChecker extends AbstractChecker {
    public function getName(): string { return 'Page Speed Hints'; }

    public function run(): array {
        $targetDir = $this->config->getTargetDir();
        $toolDir = method_exists($this->config, 'getToolDir') ? $this->config->getToolDir() : '';
        $isSelfAudit = $toolDir !== '' && realpath($targetDir) === realpath($toolDir);

        $files = FileSystem::scanDir($targetDir, ['php', 'html', 'phtml', 'twig']);
        /** @var array<string, string> $templates */
        $templates = [];
        foreach ($files as $file) {
            $rel = substr($file, strlen($targetDir) + 1);
            // Self-audit: skip tool's own source (Lesson 07 — regex containers)
            if ($isSelfAudit && preg_match('#^(src|bin|rules)/#', $rel)) continue;
            $content = @file_get_contents($file);
            if ($content === false) continue;
            if (stripos($content, '<head') === false) continue;
            // Regex-container heuristic: too many preg_match calls → not a real template
            if (substr_count($content, 'preg_match') > 10) continue;
            $templates[$file] = $content;
        }

        if (empty($templates)) {
            $this->addResult('SKIP', 'No HTML templates with <head> tag found.');
            return $this->results;
        }

        /** @var array<int, array{file: string, issue: string}> $issues */
        $issues = [];

        foreach ($templates as $file => $content) {
            $rel = substr($file, strlen($targetDir) + 1);

            // 1. Blocking scripts inside <head> (no defer/async, not ld+json)
            if (preg_match('/<head\b.*?<\/head>/is', $content, $head)) {
                preg_match_all(
                    '/<script\b(?![^>]*(?:defer|async|type=["\']application\/ld\+json["\']))[^>]*\bsrc\s*=/i',
                    $head[0],
                    $scripts
                );
                $blocking = count($scripts[0]);
                if ($blocking > 3) {
                    $issues[] = ['file' => $rel, 'issue' => "{$blocking} blocking <script src> in <head> (add defer/async)"];
                }
            }

            // 2. Many stylesheets (bundling opportunity)
            preg_match_all('/<link\b[^>]*rel=["\']stylesheet["\'][^>]*>/i', $content, $css);
            $cssCount = count($css[0]);
            if ($cssCount > 5) {
                $issues[] = ['file' => $rel, 'issue' => "{$cssCount} stylesheet links (consider bundling)"];
            }

            // 3. External <script src> without preconnect
            if (
                preg_match('/<script\b[^>]*src=["\']https:\/\//i', $content)
                && !preg_match('/rel=["\']preconnect["\']/i', $content)
            ) {
                $issues[] = ['file' => $rel, 'issue' => 'external <script> without <link rel="preconnect">'];
            }

            // 4. Oversized inline CSS (>= 5 KB)
            preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $content, $styles);
            foreach ($styles[1] as $s) {
                $bytes = strlen($s);
                if ($bytes > 5000) {
                    $issues[] = ['file' => $rel, 'issue' => 'inline <style> > ' . round($bytes / 1024) . ' KB'];
                    break;
                }
            }
        }

        if (empty($issues)) {
            $n = count($templates);
            $this->addResult('PASS', "No page speed anti-patterns in {$n} template(s).");
            return $this->results;
        }

        $list = [];
        foreach (array_slice($issues, 0, 20) as $i) {
            $list[] = "{$i['file']}: {$i['issue']}";
        }
        if (count($issues) > 20) {
            $list[] = '... and ' . (count($issues) - 20) . ' more';
        }
        $this->addResult('WARN', count($issues) . ' page speed hint(s)', ['list' => $list]);
        return $this->results;
    }
}
