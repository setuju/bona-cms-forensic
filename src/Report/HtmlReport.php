<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Report/HtmlReport.php
 * │  Purpose   : Self-contained HTML report generator (no CDN, no JS)
 * │  Author    : Jack — Jack[at]SatuRumah[dot]Net                        │
 * │  GitHub    : https://github.com/setuju                               │
 * │  Website   : https://saturumah.net                                   │
 * │  Created   : 2026-10-28                                              │
 * │  License   : Proprietary — all rights reserved                       │
 * └──────────────────────────────────────────────────────────────────────┘
 *
 * @author    Jack <Jack[at]SatuRumah[dot]Net>
 * @created   2026-10-28
 * @package   SatuRumah\Forensic\Report
 * @license   Proprietary
 */

declare(strict_types=1);
namespace SatuRumah\Forensic\Report;

final class HtmlReport {
    public static function render(
        array $results,
        string $target,
        string $outputPath,
        string $version = '2.2.5'
    ): string {
        $grouped = ['FAIL' => [], 'WARN' => [], 'PASS' => [], 'SKIP' => [], 'INFO' => []];
        foreach ($results as $r) {
            $status = $r['status'] ?? 'INFO';
            if (!isset($grouped[$status])) { $grouped['INFO'][] = $r; continue; }
            $grouped[$status][] = $r;
        }
        $counts = array_map('count', $grouped);
        $total = array_sum($counts);
        $overall = $counts['FAIL'] > 0 ? 'FAIL' : ($counts['WARN'] > 0 ? 'WARN' : 'PASS');
        $generated = date('Y-m-d H:i:s T');
        $html = self::buildHtml($grouped, $counts, $total, $overall, $target, $version, $generated);
        $dir = dirname($outputPath);
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        file_put_contents($outputPath, $html);
        return $outputPath;
    }

    private static function esc(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    private static function color(string $s): string {
        return match($s) {
            'PASS' => '#22c55e', 'WARN' => '#eab308', 'FAIL' => '#ef4444',
            'SKIP' => '#64748b', default => '#3b82f6',
        };
    }
    private static function icon(string $s): string {
        return match($s) {
            'PASS' => '✓', 'WARN' => '⚠', 'FAIL' => '✗',
            'SKIP' => '○', default => 'ℹ',
        };
    }

    private static function buildHtml(
        array $grouped, array $counts, int $total,
        string $overall, string $target, string $version, string $generated
    ): string {
        $e = fn($s) => self::esc((string)$s);
        $badge = function(string $status) use ($e) {
            $c = self::color($status);
            $i = self::icon($status);
            return "<span class=\"badge\" style=\"background:{$c}1a;color:{$c};border:1px solid {$c}55\">{$i} {$e($status)}</span>";
        };

        $sections = '';
        foreach (['FAIL', 'WARN', 'PASS', 'SKIP', 'INFO'] as $st) {
            if (empty($grouped[$st])) { continue; }
            $items = '';
            foreach ($grouped[$st] as $r) {
                $name = $e($r['name'] ?? '');
                $msg = $e($r['message'] ?? $r['detail'] ?? '');
                $details = '';
                if (!empty($r['details']['list']) && is_array($r['details']['list'])) {
                    $details = '<ul class="detail-list">';
                    foreach ($r['details']['list'] as $ln) {
                        $details .= '<li>' . $e($ln) . '</li>';
                    }
                    $details .= '</ul>';
                }
                $items .= "<li class=\"item\"><div class=\"item-head\">{$badge($st)} <strong>{$name}</strong></div><div class=\"item-msg\">{$msg}</div>{$details}</li>";
            }
            $c = self::color($st);
            $sections .= "<section class=\"group\"><h2 style=\"border-left:4px solid {$c}\">{$st} <span class=\"count\">({$counts[$st]})</span></h2><ul class=\"items\">{$items}</ul></section>";
        }

        $oc = self::color($overall);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bona Forensic — {$e($target)}</title>
<style>
:root{--bg:#0f172a;--fg:#e2e8f0;--card:#1e293b;--muted:#94a3b8;--line:#334155}
@media(prefers-color-scheme:light){:root{--bg:#f8fafc;--fg:#0f172a;--card:#fff;--muted:#64748b;--line:#e2e8f0}}
*{box-sizing:border-box}
body{margin:0;font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",system-ui,sans-serif;background:var(--bg);color:var(--fg);padding:24px}
.container{max-width:1100px;margin:0 auto}
header{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;margin-bottom:24px}
h1{margin:0 0 4px;font-size:22px;font-weight:700}
.meta{color:var(--muted);font-size:12px;font-family:ui-monospace,monospace}
.overall{font-size:24px;font-weight:800;padding:12px 20px;border-radius:12px;color:{$oc};background:{$oc}1a;border:2px solid {$oc}55}
.summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px;margin-bottom:24px}
.card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:14px 16px}
.card-label{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.card-value{font-size:24px;font-weight:700}
.badge{display:inline-block;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;font-family:ui-monospace,monospace}
.group{margin-bottom:28px}
.group h2{font-size:16px;margin:0 0 12px;padding:4px 0 4px 12px;font-weight:600}
.count{color:var(--muted);font-weight:400;font-size:13px}
.items{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px}
.item{background:var(--card);border:1px solid var(--line);border-radius:8px;padding:12px 14px}
.item-head{display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap}
.item-head strong{font-size:13px}
.item-msg{color:var(--muted);font-size:13px;font-family:ui-monospace,monospace}
.detail-list{margin:8px 0 0 20px;padding:0;font-size:12px;font-family:ui-monospace,monospace;color:var(--muted)}
.detail-list li{margin:2px 0}
footer{margin-top:40px;padding-top:16px;border-top:1px solid var(--line);color:var(--muted);font-size:11px;text-align:center;font-family:ui-monospace,monospace}
</style>
</head>
<body>
<div class="container">
<header>
<div>
<h1>🔬 Bona Forensic Report</h1>
<div class="meta">Target: {$e($target)}</div>
<div class="meta">Generated: {$e($generated)} · v{$e($version)}</div>
</div>
<div class="overall">{$e($overall)}</div>
</header>
<div class="summary">
<div class="card"><div class="card-label">Total</div><div class="card-value">{$total}</div></div>
<div class="card"><div class="card-label" style="color:#ef4444">Fail</div><div class="card-value" style="color:#ef4444">{$counts['FAIL']}</div></div>
<div class="card"><div class="card-label" style="color:#eab308">Warn</div><div class="card-value" style="color:#eab308">{$counts['WARN']}</div></div>
<div class="card"><div class="card-label" style="color:#22c55e">Pass</div><div class="card-value" style="color:#22c55e">{$counts['PASS']}</div></div>
<div class="card"><div class="card-label" style="color:#64748b">Skip</div><div class="card-value" style="color:#64748b">{$counts['SKIP']}</div></div>
</div>
{$sections}
<footer>Bona Forensic Enterprise · READ-ONLY audit · Self-contained HTML (no CDN)</footer>
</div>
</body>
</html>
HTML;
    }
}
