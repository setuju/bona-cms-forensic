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

## [v2.1.0] - 2026-10-26

### Added
- **BrokenLinksChecker** — detects `require`/`include` targets that don't exist on disk (runtime safety)

## [Unreleased]

### Planned
- Lighthouse CLI integration for deep page speed
- Grafana dashboard template
- Pre-commit hook generator
- Support for additional YAML rules (CSRF, LFI, XXE)

## [2.0.0] — 2026-10-26

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

## [1.0.0] — 2026-10-26

### Added
- Initial release with 27 sections in single-file `bona-forensic.php`
- Statistics, dependency graph, orphan detection, security heuristics
- Custom YAML rules
- GitHub Actions workflow
