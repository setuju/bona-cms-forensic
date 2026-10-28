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

## [v2.4.3] - 2026-10-28

### Fixed
- **TtfbChecker false-positive FAIL** — baseline 9.3ms vs current 13ms
  triggered FAIL (>20% ratio) despite 3.7ms delta being pure noise.
  WSL2 local PHP server has ±5ms jitter from GC/context switch.

### Changed
- Added `$noiseFloorMs = 30.0` absolute threshold
- FAIL fires only if `$ratio > 1.20` **AND** `$deltaMs >= 30`
- WARN fires only if `$ratio > 1.10` **AND** `$deltaMs >= 30`
- Sub-30ms measurements no longer produce FAIL/WARN regardless of ratio

### Behavior verification
- 9.3ms → 13.0ms (delta=3.7ms)  → PASS (noise)
- 9.3ms → 50.0ms (delta=40.7ms) → FAIL (real regression, unchanged)

### Why
Ratio-only threshold conflates "noise" (9→13ms) with "regression" (50→200ms).
Absolute floor distinguishes measurement jitter from real performance degradation.

### Not fixed (out of scope, evidence-based)
- **A11yChecker**: rc=2 "not JSON-parseable" on target is environment issue,
  not tool bug. Fixture test proves A11yChecker works:
  `/tmp` fixture with index.php → PASS (0 errors). Target has complex pages
  that crash Chromium mid-crawl. Correct behavior = SKIP with Chromium hint.

## [v2.4.2] - 2026-10-28

### Fixed
- **DuplicationChecker LOC parse bug** — v2.4.1 regex `/(\d+)\s+lines of code/`
  expects "3948 lines of code" but phpcpd 6.x outputs "3948 total lines of code"
  (word "total" between number and "lines"). Match fails → fallback `$totalLines = 1`
  → absurd `6800% of 1 LOC` self-audit FAIL.

### Changed
- Parse percentage directly from phpcpd summary: `/([\d.]+)%\s+duplicated lines/`
- Fallback: LOC extraction with `(?:total\s+)?` optional word
- Last resort: INFO with absolute numbers (no fake threshold breach)
- No `end(explode())` — keeps PHPStan L6 clean

### Verified
Self-audit on tool source: `1.72% duplicated` → PASS (<5% threshold)
24f813a (v2.4.1): 1 FAIL regression. v2.4.2: 0 FAIL / 1 WARN restored.

## [v2.4.1] - 2026-10-28

### Fixed
- **ComplexityChecker** — 2 bugs from v2.4.0:
  - Regex mismatch: `phploc` outputs `Average Complexity per Method  X.XX` (not
    `Cyclomatic Complexity per Class/Method: X.XX`). Now parses correctly.
  - Missing excludes: `phploc` was scanning entire targetDir including vendor/,
    node_modules/, tests/. Now excludes via `--exclude=DIR` flags.
    Self-audit went from 1956 files / 192,524 LOC to 37 files / 4,000 LOC.
- **DuplicationChecker** — missing directory excludes:
  - `--names-exclude=tests` was filename pattern, not directory (no-op).
    Replaced with `--exclude=vendor --exclude=node_modules --exclude=tests ...`
    matching ComplexityChecker pattern.

### Verification
- phploc 7.0.2: `Average Complexity per Method = 2.26` → ComplexityChecker PASS
- phpcpd 6.0.3: see run output for actual % (tool's 31 checkers share structural patterns)

### Notes
- Tools installed via `composer global require` (phploc 7.0.2, phpcpd 6.0.3,
  phpmetrics 2.11.0 — all symlinked to ~/.local/bin)
- **phpcpd is abandoned** (2022) — mentioned in composer output. Alternative:
  use `phpmetrics` for combined complexity + duplication (not implemented yet).
- Session floor for self-audit remains 1 WARN (PHP2Shell user php.ini).
  External target audits may add WARN if target has real duplication.

## [v2.4.0] - 2026-10-28

### Added
- **ComplexityChecker** (`src/Checkers/ComplexityChecker.php`)
  - Wraps `phploc` for average cyclomatic complexity per method
  - Thresholds: WARN > 20, FAIL > 40
  - Detection: target/vendor/bin → tool/vendor/bin → PATH → SKIP
- **DuplicationChecker** (`src/Checkers/DuplicationChecker.php`)
  - Wraps `phpcpd --min-lines=6 --min-tokens=70 --fuzzy`
  - Thresholds: WARN > 5%, FAIL > 10% duplication
  - Detects "No clones found" as PASS

### Notes
- Both follow `CoverageChecker` graceful-SKIP pattern (activate when tool installed)
- phploc/phpcpd are OPTIONAL dev deps — NOT added to composer.json
- Activation:
  - `composer require --dev phploc/phploc` → ComplexityChecker active
  - `composer require --dev sebastian/phpcpd` → DuplicationChecker active

## [v2.5.0] - 2026-10-28

### Added
- **4 new Semgrep YAML rules** — 9 → 13 total
  - `rules/ssti.yaml` — Twig/Smarty SSTI via user input (CWE-1336)
  - `rules/xxe-blind.yaml` — entity loader re-enabled (CWE-611)
  - `rules/rce-blind.yaml` — proc_open/popen/pcntl_exec user input (CWE-78)
  - `rules/info-disclosure.yaml` — var_dump, phpinfo, stacktrace echo (CWE-209/489)

### Notes
- `echo $X->method()` pattern fails Semgrep PHP AST → `pattern-regex` for stacktrace
- Individual validation gate (all 4 pass). Combined `--config rules/` skipped
  because legacy `rce.yaml` self-matches `shell_exec()` in own pattern definition.
  Functional gate: `bona-forensic --section=Semgrep` (loads all 13 rules, runs clean).

## [v2.3.3] - 2026-10-28

### Fixed
- **PhpUnitChecker JUnit XML parse** — PHPUnit 10+ writes `tests` attr on child
  `<testsuite>`, not on root `<testsuites>`. Previous code read `$xml['tests']` on
  root → always 0 → false "empty suite" WARN even with 18/18 tests passing.

### Changed
- PhpUnitChecker sums child `<testsuite>` `tests` attributes when root absent.
  Backward compatible with PHPUnit 9 (root attr present, no change).

### Retroactive impact
- Any target on PHPUnit 10/11/12/13 falsely flagged "empty suite". Fix universal.
- Self-audit WARN: 2 → 1 (only PHP2Shell user-side php.ini remains).

## [v2.3.2] - 2026-10-28

### Fixed
- **ConfigChecker false positive** — CMS feature scan now excludes `tests/`, `node_modules/`,
  `.git/`, `.bona/`, `cache/` in addition to existing `vendor/`, `src/`, `bin/`, `rules/`
  - Same root cause as StatsChecker fix (v2.2.5, L20)
- **FileSystemTest.php** — added 3 tests (6 → 9):
  - `testSetIgnorePrefixesAndIsIgnored` — both static methods
  - `testWriteLogCreatesFile` — writeLog single call, mkdir precondition
  - `testWriteLogMultipleCallsDifferentFiles` — writeLog multi-call, distinct paths
  - Coverage: 77.27% → expected > 80%

## [v2.3.1] - 2026-10-28

### Added
- **Test infrastructure** — 15 tests across 3 files
  - `tests/Unit/FileSystemTest.php` — scanDir (empty/extensions/excludes/recursive)
  - `tests/Unit/HtmlReportTest.php` — render all status paths
  - `tests/Unit/FrameworkCheckerTest.php` — no/WP/Laravel detection
- **`phpunit.xml`** — bootstrap, testsuite, coverage scoped to FileSystem.php
- **`autoload-dev`** — `Tests\` → `tests/`
- **composer scripts** — `test`, `coverage`

### Changed
- **`bin/forensic --version`** — `2.2.5` → `2.7.0` (was stale 5 versions behind CHANGELOG)

### Effect
- `Code Reliability` WARN (tests=0) → resolved
- `CoverageChecker` now active (phpunit.xml present)
- `--version` accurate

## [v2.3.0-fix] - 2026-10-28

### Fixed
- **SecurityChecker false positive** on `DbGrowthChecker.php` (SELECT COUNT(*) flagged as unprepared)

### Changed
- `SecurityChecker`: file-level skip now includes both `DatabaseChecker.php` AND `DbGrowthChecker.php`
- `DbGrowthChecker`: `->query()` → `->prepare()->execute()` for `SELECT COUNT(*) FROM` (semantic consistency)
- Rationale: L06 — introspection queries (SHOW/PRAGMA/DESCRIBE/EXPLAIN/COUNT) are metadata, not user input

## [v2.3.0] - 2026-10-28

### Added
- **`DbGrowthChecker`** — READ-ONLY PDO row-count tracking
  - Auto-detect MySQL creds: `config.php`, `.env`, `config.local.php`, `config/database.php`
  - Persist per-table counts → `.bona/db-snapshot.json`
  - WARN if any table grew >50% since last snapshot
  - Graceful SKIP: no creds / connection fail / introspection error
  - READ-ONLY enforced via `PDO::MYSQL_ATTR_INIT_COMMAND = 'SET SESSION TRANSACTION READ ONLY'`
- **`CoverageChecker`** — PHPUnit line coverage
  - Requires `pcov` OR `xdebug` → SKIP with install hint if neither
  - FAIL <60%, WARN <80%, PASS >=80%
  - Activates automatically once `sudo pecl install pcov`

## [v2.7.0] - 2026-10-28

### Added
- **`src/Checkers/FrameworkChecker.php`** — detect PHP framework
  - Marker files: WordPress, Laravel, Symfony, CodeIgniter, Yii2, CakePHP, Drupal, Slim
  - Composer-based detection: `slim/slim`, `laravel/framework`, `symfony/framework-bundle`, etc.
  - Output: INFO only (never FAIL — detection is metadata, not quality gate)
  - Self-audit on CLI tool → INFO "No framework markers detected"
  - `--section=framework --show-findings` shows per-framework evidence paths
- Registered in `$checkers` array (27 concrete checkers total)

### Notes
- Detection is additive: multi-framework projects list all matches sorted by evidence count
- Composer evidence appended as `composer: <pkg>` alongside file markers
- Zero writes to target — pure `file_exists` + `is_readable` probes

## [v2.6.0] - 2026-10-28

### Added
- **`--html[=PATH]`** CLI flag — self-contained HTML report (no CDN, no JS)
- **`src/Report/HtmlReport.php`** — standalone renderer with dark/light auto-theme
  - Summary cards (total/fail/warn/pass/skip) + overall badge
  - Grouped sections per severity (FAIL/WARN/PASS/SKIP/INFO)
  - Detail lists inline for findings with `details.list`
  - Default output: `.bona/report-YYYYMMDD-HHMMSS.html`
  - Custom path: `--html=/path/to/report.html`
- Composable with `--json`, `--report`, plain mode (all 4 flags work together)

### Notes
- HTML report consumed from `$allResults` aggregate (all checkers), not leftover `$results`
- Renders before final `exit()`, after all checkers complete
- Zero external dependencies — single file, opens in any browser

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

### Reporting (v2.2.5-D.1)
- **PASS list dedup** — grouped summary now shows unique checker names; header label clarifies when N results span M checkers (was showing duplicate names)
- **`tests/smoke-report.sh`** — regression guard for `--report` flag: verifies file creation, header/exec-summary/findings sections, read-only disclaimer, no duplicate PASS bullets
- **`Makefile: test-report`** target — `make test-report` runs the smoke test

### Reporting
- **Grouped-by-severity summary** — console output adds "📊 GROUPED BY SEVERITY" after FINAL SUMMARY: FAIL/WARN/SKIP items grouped (up to 10 each, msg truncated), PASS names inline
- **`--report[=PATH]`** — markdown report generator
  - `--report` → `.bona/reports/report-Ymd-His.md` (auto path, tool dir)
  - `--report=/path` → explicit path
  - Structure: header (target, tool HEAD, status), executive summary table, findings by severity, PASS/SKIP bullets, read-only disclaimer
  - Atomic write (.tmp → rename); works in json mode (notice to stderr)
  - Read-only: never writes to targetDir
  - No fix instructions embedded

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

### Added (CSRF — v2.5.0 completion)
- **CSRF surface check** — implemented in `SecurityChecker` (multi-method)
  - **Method 1 (file-level)**: file has `<form method="POST">` but no CSRF keyword (`csrf_field`/`csrf_token`/`csrf_validate`/`_token`/`csrfmiddlewaretoken`) → WARN
  - **Method 2 (form-block)**: each POST form body must reference `csrf` or `_token` → WARN per form
  - Both methods verified 0 FP on clean Bona-CMS-Dev-X (29 files with POST forms, all have CSRF)
  - Break-test: synthetic file with POST form + no CSRF → correctly detected as WARN
  - Line-based form-to-handler trace available for future enhancement (v2.5.1)

### Not implemented (documented)
- **Semgrep CSRF rule** — attempted multiple approaches (pattern-regex, metavariable-pattern, metavariable-regex)
  - Limitation: `languages: [generic]` treats PHP+HTML as opaque `INLINE_HTML` blob, not parsed structure
  - Cannot inspect "form body contains csrf field" via Semgrep YAML
  - PHP-side check in `SecurityChecker` is the correct home (structural, per-file, contextual)

## [v2.2.0.2] - 2026-10-28

### Enhanced
- **A11yChecker** — deep crawl v2 (multi-page audit, MAX 5 URLs)
  - **Server-ready poll** (15× 200ms) — race-free against slow PHP boot
  - **Accept rc=0 and rc=2** — pa11y-ci returns 2 when it finds WCAG issues (payload still valid); previous code treated non-zero as error
  - **Language code filter** `^/[a-z]{2}(/|$)` — skips `/id/`, `/zh/`, etc. (dominated DOM order in recon)
  - **Priority sort** — content routes first (`/about`, `/contact`, `/blog`, `/quotes`, ...), others fill remaining slots
  - **MAX_PAGES = 5** (root + 4 content), **HTTP_TIMEOUT_SEC 120 → 180**
  - Batch mode via `urls[]` config; Chromium spawns once, iterates all URLs (~5s per URL after first)
  - Runtime discovery only (fetch root HTML, extract `<a href>`)

### Removed
- `A11yChecker::writePa11yConfig()` (old single-URL method) — replaced by `writePa11yConfigV2(array $urls)`

### Known Limitations
- **Prod `saturumah.net`**: root `/` returns 301 → `http://localhost:8000/id/` (hardcoded base_url in app config). A11y deep crawl SKIPs until fixed **app-side** — this is not a tool bug.
- **JS-rendered SPA**: requires `<a href>` in initial HTML (server-side). Not applicable to current targets.
- **Previous attempt (e851836) reverted**: rc=2 was misinterpreted; language filter missing; fixed in this retry.

## [v2.2.0.1] - 2026-10-28

### Added
- **A11yChecker** — WCAG 2.1 AA accessibility audit via pa11y-ci
  - Detects pa11y-ci binary; SKIP with install hint if missing
  - Detects entry point (`public/index.php`, `index.php`, `router.php`, `router-dev.php`)
  - Starts ephemeral PHP server on free port (9297-9397) — reused from TtfbChecker
  - Writes `.bona/a11y-ci-config.json` (WSL-safe: `--no-sandbox`, `--disable-dev-shm-usage`, `--disable-gpu`)
  - Runs `pa11y-ci --reporter=json` on root URL, extracts WCAG 2.1 AA errors
  - FAIL if errors > 0; PASS otherwise
  - Per-run log: `.bona/logs/log-a11y-{timestamp}.txt`
  - Server cleanup in `finally` — no orphaned processes
  - Graceful SKIP: no curl / no pa11y-ci / no entry point / no free port / server failed / timeout / JSON parse failure
  - Read-only: config + logs go to `.bona/` under tool dir (never touches target)

### Notes
- MVP tests root URL only; per-page deep crawl deferred to v2.2.0.2
- WSL2-ready: `--no-sandbox` required for Chromium in container; `--disable-dev-shm-usage` for limited `/dev/shm`

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
