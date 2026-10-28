<!--
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : CHANGELOG.md
 * │  Purpose   : Version history and release notes
 * │  Author    : Jack — Jack[at]SatuRumah[dot]Net                        │
 * │  GitHub    : https://github.com/setuju                               │
 * │  Website   : https://saturumah.net                                   │
 * │  Created   : 2026-10-26                                              │
 * │  License   : Proprietary — all rights reserved                       │
 * └──────────────────────────────────────────────────────────────────────┘
 *
 * @author    Jack <Jack[at]SatuRumah[dot]Net>
 * @created   2026-10-26
 * @package   SatuRumah\Forensic\GitHub
 * @license   Proprietary
 */
-->

# Changelog

All notable changes to Bona Forensic will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [v2.2.5] - 2026-10-28

### Added
- **FileSystem::setIgnorePrefixes / isIgnored** — global path-prefix allowlist used by `scanDir()`
- **`--ignore-path=PREFIX`** CLI flag — e.g. `--ignore-path=scripts` to skip CVA-relative scripts

### Changed
- **SecurityChecker** — skip `$var = match(...) { 'lit' => '...' }` whitelists when reporting unprepared queries (fixes false positive on `admin/quotes.php:110`)
- **LogChecker** — group errors by `(type, source:line, message)` signature; report top-5 signatures + unique count instead of raw total (actionable > noisy)

### Fixed
- **False positive**: `admin/quotes.php:110` no longer flagged as "unprepared statement" — `$filterSql` is a `match()` selector with hardcoded literals
- **Log noise**: 1002 raw errors → grouped by signature (unique actionable set)

### Performance
- **MaintenanceChecker** — *deferred to v2.2.5-A.2*: initial `xargs -P 8` attempt regressed (shell-spawn overhead dominates for tiny files: 4.3s → 10.8s). Reverted to sequential; maintenance is not the bottleneck (semgrep is). Optimizations to try: `xargs -n 30` batching, `pcntl_fork`, or `token_get_all` pre-check.
- **SemgrepChecker** — append `--timeout=45 --jobs=4 [--max-target-bytes=1000000]` when supported by installed semgrep version (probed at patch time; zero behavior change if none apply)
- **bin/forensic** — `--skip=SECTION` flag (comma-separated, substring match on getName); complement to `--section=`, useful in prod where slow/network-bound sections aren't critical

### Notes
- Broken-links: `--ignore-path=scripts` opt-in reduces 17 FAIL → 0 (scripts are CWD-relative by design)
- Zero behavior change for callers not using `--ignore-path`

## [v2.5.0] - 2026-10-26

### Added
- **Semgrep custom rules expansion (5 new)**: `xxe`, `ssrf`, `deserialize`, `lfi`, `open-redirect`
  - Coverage: CWE-611, CWE-918, CWE-502, CWE-98, CWE-601
  - Auto-discovered by `SemgrepChecker` — no code changes needed
  - Zero false positives on self-audit and Bona-CMS-Dev-X target
  - Superglobal-only sinks (`$_GET`/`$_POST`/`$_REQUEST`/`$_COOKIE`)
    to avoid FP on variable-based usage

### Deferred
- **CSRF rule** — deferred to v2.4 (AST-based dedicated checker).
  Initial line-based regex produced 61 false positives on Bona-CMS-Dev-X
  (every POST form flagged regardless of token presence).
  `AdvancedSecurityChecker` provides runtime CSRF surface checks
  in the meantime.

## [v2.2.0] - 2026-10-28

### Added
- **TtfbChecker** — real TTFB measurement (median of 5× cURL against ephemeral localhost server)
  - Auto-detects entry point (`public/index.php`, `index.php`, `router-dev.php`, `router.php`)
  - Baseline stored in `.bona/ttfb-baseline.json` (in tool dir — never touches target)
  - Thresholds: regression > 20% → FAIL, > 10% → WARN, ≤ baseline → PASS (+ update if improved)
  - First run establishes baseline (PASS)
  - Per-run log in `.bona/logs/log-ttfb-{timestamp}.txt` (samples, median, baseline)
  - Server cleanup via `pkill -f 'php -S 127.0.0.1:{port}'` in `finally` — no orphaned processes
  - Graceful SKIP: no entry point / no curl / server failed / <3 valid samples

## [v2.1.0] - 2026-10-26

### Added
- **BrokenLinksChecker** — detects `require`/`include` targets that don't exist on disk (runtime safety)
- **PageSpeedChecker** — detects blocking scripts, many stylesheets, missing preconnect, oversized inline CSS
- **AssetChecker** — inventory + size stats; flags files > 500 KB (WARN) and total > 50 MB (FAIL)
- **ReliabilityChecker** — quality baseline metrics (tests count, phpunit.xml presence, composer deps count)

## [Unreleased]

### Planned
- Lighthouse CLI integration for deep page speed
- Grafana dashboard template
- Pre-commit hook generator
- Support for additional YAML rules (CSRF, LFI, XXE)

## [v2.0.0] — 2026-10-26

### Added
- **18 modular checkers** (PSR-4 architecture)
- **DatabaseChecker**: READ-ONLY schema audit + SEO DB audit
- **Php2ShellChecker**: CVE-2026-109618 / CVE-2026-109619 detection
- **SemgrepChecker**: SAST with 4 custom YAML rules (SQLi, XSS, RCE, PHP2Shell)
- **PhpUnitChecker**: Read-only test suite runner
- **SmokeTestChecker**: HTTP endpoint smoke test
- **SeoChecker (v2)**: 13-point comprehensive SEO with dynamic detection
- **IntegrityChecker**: MD5 proof of read-only guarantee
- **SnapshotChecker**: Diff across audit runs
- `--section=NAME` filter for single checker
- `--json` output for dashboard integration
- Mermaid macro graph (folder-to-folder)
- File contract header on all 30 project files

### Fixed
- `T_STRING_CONCAT` undefined constant (PHP 8 compat)
- Orphan false positive for admin/api/cron/debug
- SEO false positive for mailer/error_handler
- XSS false positive on `===` string comparison
- `bin/forensic` shebang placement
- GITHUB_TOKEN env override → `env -u GITHUB_TOKEN -u GH_TOKEN` pattern

### Security
- Patched log directory leak (writes only to tool, never to target)
- `Config::toolDir` hardcoded, prevents self-scan confusion
- Verified read-only via 461-file MD5 comparison

## [v1.0.0] — 2026-10-26

### Added
- Initial release with 27 sections in single-file `bona-forensic.php`
- Statistics, dependency graph, orphan detection, security heuristics
- Custom YAML rules
- GitHub Actions workflow
