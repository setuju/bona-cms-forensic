<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Checkers/SeoChecker.php
 * │  Purpose   : Comprehensive SEO analysis for HTML pages
 * │  Author    : Jack — Jack[at]SatuRumah[dot]Net                        │
 * │  GitHub    : https://github.com/setuju                               │
 * │  Website   : https://saturumah.net                                   │
 * │  Created   : 2026-10-26                                              │
 * │  License   : Proprietary — all rights reserved                       │
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

class SeoChecker extends AbstractChecker {
    private const DYNAMIC_PATTERNS = [
        '/<\?=\s*\$seo/i',
        '/<\?php\s+echo\s+\$seo/i',
        '/<\?=\s*\$page->(title|meta|seo|og)/i',
        '/<\?=\s*e\(\s*\$page->(title|meta|seo|og)/i',
        '/<\?=\s*\$monologue\[[\'"]seo/i',
        '/\$\w+\[[\'"]seo_/i',
        '/\$\w+\[[\'"]meta_/i',
        '/get_setting\s*\(\s*[\'"]seo/i',
        '/getSetting\s*\(\s*[\'"]seo/i',
        '/get_setting\s*\(\s*[\'"]meta/i',
        '/getSetting\s*\(\s*[\'"]meta/i',
        '/\$config\s*\[\s*[\'"]seo/i',
        '/\$settings\s*\[\s*[\'"]seo/i',
        '/\$meta_title|\$meta_description|\$seo_title|\$seo_desc/i',
        '/\$og_title|\$og_image|\$og_description|\$canonical_url/i',
        '/->getMeta|->getSeo|->meta\(/i',
    ];

    public function getName(): string { return 'SEO Comprehensive Analysis'; }

    public function run(): array {
        $files = FileSystem::scanDir(
            $this->config->getTargetDir(),
            ['php', 'html', 'phtml'],
            ['vendor', 'node_modules', '.git', 'includes', 'system', 'lib', 'core',
             'mailers', 'cron', 'api', 'migrations', 'storage', 'cache', 'tests', 'debug']
        );

        $stats = ['public' => 0, 'admin' => 0, 'auth' => 0];
        $allIssues = [];
        $issuesByType = [];
        $pagesWithIssues = 0;

        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content === false) continue;

            if (!preg_match('/<html[\s>]|<!DOCTYPE\s+html/i', $content)) continue;
            if (preg_match('/(mailer|email_?template|error_?handler|layout_?partial)/i', basename($file))) continue;

            $rel = substr($file, strlen($this->config->getTargetDir()) + 1);
            $category = $this->getPageCategory($rel);
            if ($category === 'api') continue;

            $stats[$category] = ($stats[$category] ?? 0) + 1;
            $isDynamic = $this->hasDynamicSeo($content);

            $pageIssues = $this->analyzeSeo($content, $rel, $category, $isDynamic);
            if (!empty($pageIssues)) {
                $pagesWithIssues++;
                foreach ($pageIssues as $issue) {
                    $allIssues[] = "{$rel}: {$issue}";
                    $type = trim(preg_replace('/\d+/', 'N', $issue));
                    $type = preg_replace('/\(.*?\)$/', '', $type);
                    $type = trim($type);
                    $issuesByType[$type] = ($issuesByType[$type] ?? 0) + 1;
                }
            }
        }

        $totalPages = array_sum($stats);
        $this->addResult('INFO', "Analyzed {$totalPages} HTML pages (public: {$stats['public']}, admin: {$stats['admin']}, auth: {$stats['auth']}).");

        if ($totalPages === 0) {
            $this->addResult('SKIP', 'No complete HTML pages found.');
            return $this->results;
        }

        if (empty($allIssues)) {
            $this->addResult('PASS', "All {$totalPages} pages have comprehensive SEO compliance.");
        } else {
            $this->addResult('WARN', count($allIssues) . ' SEO issues across ' . $pagesWithIssues . ' pages.');
            foreach ($issuesByType as $type => $count) {
                $this->addResult('INFO', "  - {$type}: {$count}");
            }
            $this->addResult('WARN', 'Detailed findings:', ['list' => array_slice($allIssues, 0, 30)]);
            if (count($allIssues) > 30) {
                $this->addResult('INFO', '... dan ' . (count($allIssues) - 30) . ' lainnya. Full log di .bona/logs/.');
            }
            FileSystem::writeLog($this->config->getLogDir(), 'seo-comprehensive', $allIssues);
        }

        return $this->results;
    }

    private function getPageCategory(string $rel): string {
        if (preg_match('#^admin/#i', $rel)) return 'admin';
        if (preg_match('#^(login|register|forgot-password|reset-password|logout|install)\.php$#i', $rel)) return 'auth';
        if (preg_match('#^api/#i', $rel)) return 'api';
        return 'public';
    }

    private function hasDynamicSeo(string $content): bool {
        foreach (self::DYNAMIC_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) return true;
        }
        return false;
    }

    private function analyzeSeo(string $content, string $rel, string $category, bool $isDynamic): array {
        $issues = [];
        $isPublic = ($category === 'public');
        $needsNoindex = in_array($category, ['admin', 'auth'], true);

        // Title
        if (!preg_match('/<title\b[^>]*>([^<]*)<\/title>/is', $content, $m)) {
            if (!$isDynamic) $issues[] = "Missing <title>";
        } elseif (trim($m[1]) === '' && !$isDynamic) {
            $issues[] = "Empty <title>";
        } else {
            $tl = strlen(trim($m[1]));
            if ($tl > 0 && ($tl < 20 || $tl > 70)) $issues[] = "<title> length {$tl} (optimal 40-60)";
        }

        // Meta description
        if (!preg_match('/<meta\s+name=["\']description["\'][^>]*content=["\']([^"\']*)["\']/i', $content, $m)) {
            if (!$isDynamic) $issues[] = "Missing <meta description>";
        } elseif (trim($m[1]) === '' && !$isDynamic) {
            $issues[] = "Empty <meta description>";
        } else {
            $dl = strlen(trim($m[1]));
            if ($dl > 0 && ($dl < 70 || $dl > 170)) $issues[] = "<meta description> length {$dl} (optimal 120-160)";
        }

        // Public-only checks
        if ($isPublic) {
            if (!preg_match('/<link\s+rel=["\']canonical["\']/i', $content) && !$isDynamic) {
                $issues[] = "Missing canonical URL";
            }
            foreach (['og:title', 'og:description', 'og:image', 'og:url'] as $og) {
                if (!preg_match('/<meta\s+property=["\']' . preg_quote($og) . '["\']/i', $content) && !$isDynamic) {
                    $issues[] = "Missing {$og}";
                }
            }
            if (!preg_match('/<meta\s+name=["\']twitter:card["\']/i', $content) && !$isDynamic) {
                $issues[] = "Missing twitter:card";
            }
            if (!preg_match('/<script\s+type=["\']application\/ld\+json["\']/i', $content)) {
                $issues[] = "Missing JSON-LD structured data";
            }
            if (!preg_match('/<html[^>]+lang=["\'][a-z]{2}/i', $content) && !$isDynamic) {
                $issues[] = "Missing lang attribute on <html>";
            }
        }

        // Common
        $h1 = preg_match_all('/<h1\b/i', $content);
        if ($h1 === 0) $issues[] = "No <h1> tag";
        elseif ($h1 > 1) $issues[] = "Multiple <h1> ({$h1}, should be 1)";

        preg_match_all('/<img\b[^>]*>/i', $content, $imgs);
        $noAlt = 0;
        foreach ($imgs[0] as $img) if (!preg_match('/\balt\s*=/i', $img)) $noAlt++;
        if ($noAlt > 0) $issues[] = "{$noAlt} <img> without alt attribute";

        if (!preg_match('/<meta\s+name=["\']viewport["\']/i', $content)) {
            $issues[] = "Missing viewport meta tag";
        }

        // Auth/admin: MUST be noindex. Public: MUST NOT be noindex.
        if ($needsNoindex) {
            if (!preg_match('/<meta\s+name=["\']robots["\'][^>]*content=["\'][^"\']*noindex/i', $content)) {
                $issues[] = "Admin/auth page missing 'noindex' directive (SEO risk)";
            }
        } else {
            if (preg_match('/<meta\s+name=["\']robots["\'][^>]*content=["\'][^"\']*noindex/i', $content)) {
                $issues[] = "Public page has 'noindex' (verify intentional)";
            }
        }

        return $issues;
    }
}
