<!--
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : README.md
 * │  Purpose   : Project overview, install, checkers, CLI reference      │
 * │  Author    : Jack — Jack[at]SatuRumah[dot]Net                        │
 * │  GitHub    : https://github.com/setuju                               │
 * │  Website   : https://saturumah.net                                   │
 * │  Created   : 2026-10-26                                              │
 * │  License   : Proprietary — all rights reserved                       │
 * └──────────────────────────────────────────────────────────────────────┘
 *
 * @author    Jack <Jack[at]SatuRumah[dot]Net>
 * @created   2026-10-26
 * @package   SatuRumah\Forensic
 * @license   Proprietary
 */
-->

<div align="center">

# 🛡️ Bona Forensic

**READ-ONLY forensic audit tool for PHP applications**

[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%20%7C%208.3%20%7C%208.4-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![PHPStan Level 6](https://img.shields.io/badge/PHPStan-Level%206-brightgreen)](https://phpstan.org/)
[![Semgrep](https://img.shields.io/badge/Semgrep-9%20custom%20rules-orange)](https://semgrep.dev/)
[![License: Proprietary](https://img.shields.io/badge/License-Proprietary-red)](./LICENSE)

*Static analysis · dependency mapping · security (OWASP Top 10 + CWE Top 25) · SEO · Core Web Vitals · runtime metrics · read-only proof*

Built for [saturumah.net](https://saturumah.net) · Maintained by [@setuju](https://github.com/setuju)

</div>

---

## 📖 Table of Contents

- [Why Bona Forensic?](#-why-bona-forensic)
- [Highlights](#-highlights)
- [Quick Start](#-quick-start)
- [Requirements](#-requirements)
- [The 26 Checkers](#-the-26-checkers)
- [CLI Reference](#-cli-reference)
- [Read-Only Guarantee](#-read-only-guarantee)
- [Semgrep Rules](#-semgrep-rules)
- [CI/CD Integration](#-cicd-integration)
- [Project Structure](#-project-structure)
- [Roadmap](#-roadmap)
- [Troubleshooting](#-troubleshooting)
- [Contributing](#-contributing)
- [License](#-license)

---

## 🎯 Why Bona Forensic?

Most PHP audit tools either (a) **write to your project** (composer install, cache warmup) or (b) **shuffle you between 10 different tools** (phpcs, phpstan, semgrep, lighthouse, …). Bona Forensic is different:

| Property | Guarantee |
|---|---|
| **Read-only** | MD5 snapshot verified before & after — **zero writes** to target (proven by `IntegrityChecker`) |
| **Single command** | `./bin/forensic --root=/path/to/app` — 26 checkers, one pass |
| **Modular** | PSR-4 architecture, one class per checker, PHPStan Level 6 |
| **Enterprise scope** | Static analysis + dependency mapping + runtime checks + SEO/CWV + DB audit + SAST |
| **CI-friendly** | Exit 1 on any FAIL, JSON output for dashboards |
| **False-positive aware** | Context-aware whitelists (file-level `escapeshellarg`, introspection queries, regex containers) |

> **Read-only is the core guarantee.** Every check can be re-run safely. Logs, snapshots, and baselines live in `toolDir/.bona/` — **never** in the target directory.

---

## ✨ Highlights

- **26 checkers** across 5 categories (static, dependency, frontend, runtime, integrity)
- **13 Semgrep rules** covering CWE-89 (SQLi), CWE-79 (XSS), CWE-94 (RCE), CVE-2026-109618/109619 (php2shell), CWE-611 (XXE), CWE-918 (SSRF), CWE-502 (deserialize), CWE-98 (LFI), CWE-601 (open redirect)
- **OWASP Top 10 + CWE Top 25** alignment in `SecurityChecker` and `AdvancedSecurityChecker`
- **13-point SEO analysis** + `og:*`, Twitter Card, JSON-LD, canonical, hreflang, `noindex`
- **CLS prevention** — image/iframe width/height, font-display, aspect-ratio
- **Core Web Vitals hints** — blocking scripts, stylesheet count, preconnect, inline CSS size
- **Database READ-ONLY audit** — PDO `SET SESSION TRANSACTION READ ONLY`, SEO columns, PRIMARY KEY coverage
- **PHP2Shell CVE mitigation check** — CVE-2026-109618 / CVE-2026-109619
- **Runtime HTTP checks** — headers, cookies, upload magic-bytes, CORS, JWT (real `cURL` against ephemeral localhost server)
- **MD5 integrity proof** — evidence that target was never modified
- **Snapshot & diff** — track additions / removals / new orphans across runs

---

## 🚀 Quick Start

```bash
# 1. Clone & install
git clone https://github.com/setuju/bona-cms-forensic.git
cd bona-cms-forensic
composer install

# 2. Full audit (human-readable)
./bin/forensic --root=/path/to/php/app --show-findings

# 3. CI/CD mode (exit 1 on any FAIL)
./bin/forensic --root=/path/to/php/app --ci

# 4. JSON output (for dashboards, Grafana, etc.)
./bin/forensic --root=/path/to/php/app --json > report.json

# 5. Single checker (filter by display name, case-insensitive substring)
./bin/forensic --root=/path/to/php/app --section=security --show-findings
./bin/forensic --root=/path/to/php/app --section=broken --show-findings
./bin/forensic --root=/path/to/php/app --section=seo --show-findings
```

**Self-audit** (audit the tool itself — proves read-only guarantee):

```bash
./bin/forensic --root=. --ci
# → 0 FAIL, N WARN (WARN only — informational signals, not gates)
```

---

## 📦 Requirements

| Component | Minimum | Notes |
|---|---|---|
| **PHP** | 8.2 | 8.2 / 8.3 / 8.4 CI-tested (matrix). 8.4.27+ recommended (safe from php2shell CVEs) |
| **Extensions** | `curl`, `json`, `mbstring`, `pdo`, `pdo_mysql`, `tokenizer`, `fileinfo`, `xml` | All standard |
| **Composer** | 2.6+ | For autoload + PHPUnit dev deps |
| **Semgrep** (optional) | 1.x | `pipx install semgrep` — for SAST rules |
| **pa11y-ci** (optional, v2.2.0+) | latest | `npm install -g pa11y-ci` — for WCAG checks |

**Target requirements:** any PHP 8.x application. No modification required.

---

## 🔍 The 26 Checkers

### Static Analysis (8)

| # | Checker | What it detects |
|---|---|---|
| 1 | **Code Statistics** | LOC, mean, median, outlier files (Z-score > 2.5) |
| 2 | **Maintenance & Code Quality** | `php -l` on all files, tech-debt markers (TODO/FIXME/HACK), class/function counts |
| 3 | **Security Heuristics** | OWASP Top 10 — SQLi, XSS, RCE, LFI, CSRF, SSRF, XXE, deserialize, weak crypto, path traversal |
| 4 | **Environment & Configuration** | `php.ini` security (display_errors, allow_url_include, session flags), PHP EOL, CVE audit |
| 5 | **PHP2Shell (CVE-2026-109618/109619)** | PHP runtime version, `memory_limit`, `post_max_size`, `session.upload_progress` |
| 6 | **Semgrep SAST** | 9 custom rules — see [rules/README.md](rules/README.md) |
| 7 | **Code Reliability** | Tests count, `phpunit.xml` presence, composer deps (prod/dev) |
| 8 | **Log Analysis** | `error.log` clustering (Fatal / Warning / Parse / Uncaught) |

### Dependency & Architecture (6)

| # | Checker | What it detects |
|---|---|---|
| 9 | **Dependency Graph** | File-to-file links + Mermaid macro graph (folder-level) |
| 10 | **Circular Dependencies** | DFS cycle detection across `require`/`include` |
| 11 | **Import Depth** | BFS from entry points — max depth from entry |
| 12 | **Centrality & God Files** | Fan-in analysis, God Files (high importers + high LOC) |
| 13 | **Orphan Detection** | Files never imported (excludes autoload, tests, views) |
| 14 | **Broken Links** | `require`/`include` targets that don't exist on disk (with line numbers) |

### Frontend / Web Vitals (4)

| # | Checker | What it detects |
|---|---|---|
| 15 | **Page Speed Hints** | > 3 blocking `<script>` in `<head>`, > 5 stylesheets, missing `preconnect`, inline CSS > 5 KB |
| 16 | **Asset Performance** | Asset inventory — count, MB total, mean/median/SD, files > 500 KB, total > 50 MB (FAIL) |
| 17 | **SEO Comprehensive** | 13-point: `<title>`, meta description, viewport, canonical, `og:*`, Twitter Card, `lang`, charset, favicon, JSON-LD, `<h1>` count, image `alt`, `noindex` on auth pages |
| 18 | **CLS Prevention** | `<img>` / `<iframe>` without `width`+`height`, `<video>` without aspect-ratio, `@font-face` without `font-display` |

### Runtime / Live (5)

| # | Checker | What it detects |
|---|---|---|
| 19 | **Advanced Security & Runtime** | HTTP headers (X-Frame-Options, HSTS, CSP, X-Content-Type-Options, Referrer-Policy), cookie flags (`Secure`, `HttpOnly`, `SameSite`), file-upload magic-bytes, wildcard CORS, hardcoded API keys, JWT verification |
| 20 | **Database Health** | READ-ONLY PDO (`SET SESSION TRANSACTION READ ONLY`), 30-table schema walk, PRIMARY KEY coverage, text-column inventory, **SEO DB audit** (missing settings, empty fields) |
| 21 | **Smoke Test** | HTTP endpoint checks against ephemeral localhost server |
| 22 | **TTFB Tracker** | ⚠️ **Skeleton (v2.2.0)** — real TTFB measurement (median 5× cURL vs `.bona/ttfb-baseline.json`) |
| 23 | **A11y (WCAG 2.1 AA)** | ⚠️ **Skeleton (v2.2.0)** — `pa11y-ci` integration, error vs warning parsing |

### Testing & Integrity (3)

| # | Checker | What it detects |
|---|---|---|
| 24 | **PHPUnit Test Suite** | `phpunit.xml` presence + test execution (skip gracefully if missing) |
| 25 | **Read-Only Integrity Check** | MD5 snapshot diff before/after — **proof target was not modified** (via `--section=integrity`) |
| 26 | **Snapshot & Diff** | New/removed files, added/removed links, new/resolved orphans since last run |

---

## 🎛️ CLI Reference

```
./bin/forensic [options]
```

| Flag | Type | Description |
|---|---|---|
| `--root=PATH` | required in scripts | Target directory (default: `cwd`) |
| `--section=NAME` | optional | Run only checkers whose display name **contains** `NAME` (case-insensitive, e.g. `security`, `seo`, `broken`, `database`) |
| `--show-findings` | flag | Print per-finding detail (file:line) — default is summary only |
| `--json` | flag | JSON output for CI/dashboards. Schema: `{summary:{...}, checks:[{name,status,detail}], extra:{...}}` |
| `--ci` | flag | Accepted for compatibility — **no-op**. Exit code already reflects FAIL count (see below) |

**Exit codes:**

| Code | Meaning |
|---|---|
| `0` | No FAIL (WARN may be present) |
| `1` | At least one FAIL |
| `2` | Bad argument / target not readable |

**Examples:**

```bash
# Full audit
./bin/forensic --root=../my-app --show-findings

# Only security (substring match "security" matches both SecurityChecker + AdvancedSecurityChecker)
./bin/forensic --root=../my-app --section=security --show-findings

# SEO only, JSON output
./bin/forensic --root=../my-app --section=seo --json | jq '.checks'

# Integrity proof (fast: MD5 diff)
./bin/forensic --root=../my-app --section=integrity
```

---

## 🛡️ Read-Only Guarantee

Bona Forensic **never writes to the target directory**. Proven two ways:

### 1. Architectural separation

```php
class Config {
    private string $targetDir;  // read-only — never written
    private string $toolDir;    // = __DIR__/../.. — logs, snapshots, baselines
}
```

Every checker receives `Config` — logs go to `toolDir/.bona/`, snapshots to `toolDir/.bona/snapshots/`. **No path is ever composed from `targetDir` for writes.**

### 2. `IntegrityChecker` — MD5 evidence

```bash
./bin/forensic --root=../my-app --section=integrity
# → ✓ Target directory TIDAK BERUBAH. N files verified read-only.
```

`IntegrityChecker` (1) snapshots MD5 of every target file, (2) runs all checkers, (3) re-hashes every file, (4) **FAILs** if any hash differs. If it passes, the target is byte-for-byte identical.

**CI usage:** add `--section=integrity` after every audit in CI. Any modification = FAIL = CI failure.

**Database checks:** `DatabaseChecker` uses `PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION TRANSACTION READ ONLY"` + `ATTR_TIMEOUT => 3`. Only `SELECT` / metadata introspection permitted.

---

## 🧪 Semgrep Rules

9 custom rules in [`rules/`](rules/) cover CWE Top 25 patterns. See [`rules/README.md`](rules/README.md) for the catalog, CWE mapping, and how to add new rules.

Auto-discovered by `SemgrepChecker` — no code changes needed when adding new YAML.

| Rule | CWE | Severity |
|---|---|---|
| `sqli.yaml` | CWE-89 | ERROR |
| `xss.yaml` | CWE-79 | ERROR |
| `rce.yaml` | CWE-94 / CWE-78 | ERROR / WARNING |
| `php2shell.yaml` | CVE-2026-109618/109619 | ERROR |
| `xxe.yaml` | CWE-611 | ERROR / WARNING |
| `ssrf.yaml` | CWE-918 | ERROR / WARNING |
| `deserialize.yaml` | CWE-502 | ERROR / WARNING |
| `lfi.yaml` | CWE-98 | ERROR |
| `open-redirect.yaml` | CWE-601 | ERROR |

**Zero-FP policy:** rules use **superglobal-only sinks** (`$_GET`/`$_POST`/`$_REQUEST`/`$_COOKIE`) — not arbitrary `$var` — to avoid false positives on legitimate variable-based usage. See [Lesson 20 in `LESSONS-LEARNED.md`](https://github.com/setuju/bona-cms-forensic).

---

## 🔗 CI/CD Integration

### GitHub Actions (recommended)

```yaml
name: Bona Forensic
on: [push, pull_request]

jobs:
  audit:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: ['8.2', '8.3', '8.4']
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: curl, xml, mbstring, pdo_mysql, tokenizer, fileinfo
          tools: composer, cs2pr

      - run: composer install --no-dev --prefer-dist --no-progress
      - run: chmod +x bin/forensic

      - name: Self-audit (must be 0 FAIL)
        run: ./bin/forensic --root=. --ci --show-findings

      - name: Audit target (upload findings)
        if: always()
        run: ./bin/forensic --root=/path/to/target --json > forensic-report.json

      - uses: actions/upload-artifact@v4
        if: always()
        with:
          name: forensic-report-${{ matrix.php }}
          path: |
            forensic-report.json
            .bona/logs/
            .bona/*.mmd
          retention-days: 14
```

### Generic CI (any runner with PHP)

```bash
composer install --no-dev --prefer-dist
chmod +x bin/forensic
./bin/forensic --root=. --ci --show-findings    # exit 1 stops pipeline on FAIL
```

**Recommended pattern:** run **two jobs** — (a) `--root=.` (self-audit, gate) and (b) `--root=/target` with `|| true` (informational, for the target's audit findings).

---

## 📁 Project Structure

```
bona-cms-forensic/
├── bin/forensic                # CLI entry point (executable)
├── src/
│   ├── Core/                   # Config, FileSystem, SnapshotManager
│   └── Checkers/               # 26 checkers + interface + abstract base
├── rules/                      # 9 Semgrep YAML rules
│   └── README.md               # ← rule catalog & add-guide
├── docs/
│   ├── REQUIREMENTS.md         # install / platform / extension matrix
│   └── ARCHITECTURE.md         # ← design, patterns, extending
├── .github/                    # workflows, issue templates, contributing, security
├── phpstan.neon                # PHPStan Level 6 + SARIF formatter
├── composer.json + composer.lock
├── Makefile                    # dev helper targets
├── README.md                   # this file
└── CHANGELOG.md
```

---

## 🗺️ Roadmap

See [`ROADMAP.md`](https://github.com/setuju/bona-cms-forensic) — summary:

| Version | Focus | Status |
|---|---|---|
| **v2.0.0** | Modular refactor, PSR-4, 18 core checkers, CI green, PHPStan L6 | ✅ DONE |
| **v2.1.0** | Feature parity — BrokenLinks, PageSpeed, Asset, Reliability checkers | ✅ DONE |
| **v2.5.0** | Semgrep rules expansion — XXE, SSRF, deserialize, LFI, open-redirect | ✅ DONE (5/6; CSRF deferred to v2.4 AST) |
| **v2.2.0** | `TtfbChecker` + `A11yChecker` real implementations, `BundleSizeChecker` | ⏭️ NEXT |
| **v2.2.5** | Performance (Semgrep timeout, parallel lint), accuracy (match-whitelist, log clustering), `--skip` flag | ⏭️ PLANNED |
| **v2.3.0** | `DbGrowthChecker`, `CoverageChecker` (pcov) | ⏭️ PLANNED |
| **v2.4.0** | `ComplexityChecker`, `DuplicationChecker`, `ContractTestChecker` | ⏭️ PLANNED |
| **v2.6.0** | Grafana dashboard, HTML report generator | ⏭️ PLANNED |
| **v2.7.0** | Framework-specific (WordPress, Laravel, Symfony) | ⏭️ PLANNED |
| **v2.8.0** | Historical trends (SQLite), Lighthouse CLI, Dockerfile scan | ⏭️ PLANNED |
| **v3.0.0** | PHAR distribution, GitHub Releases | ⏭️ PLANNED |

---

## 🩺 Troubleshooting

| Symptom | Fix |
|---|---|
| `Semgrep not installed` — checker SKIP | `pipx install semgrep` or `pip install --user semgrep` |
| `No composer.lock` — CVE audit SKIP | `composer install` in target, or ignore (CVE check is optional) |
| `Smoke test` FAIL (port collision) | Re-run — uses random ports; collision is rare |
| **Full audit slow (> 60s)** | Use `--section=NAME` to isolate. Known slow: `semgrep` (~50s), `maintenance` (~13s on 200+ files). Performance fixes tracked in v2.2.5 |
| `PHPUnit Test Suite` SKIP | Target has no `phpunit.xml` — expected for many apps |
| `TTFB Tracker` / `A11y` SKIP | Skeleton in v2.2.0 — not yet implemented |
| `Database` SKIP | Config not found or connection failed — safe to ignore in CI (target may not have DB) |
| False positive in `SecurityChecker` | See [`LESSONS-LEARNED.md` Lesson 05–07](https://github.com/setuju/bona-cms-forensic) — report with file:line |

---

## 🤝 Contributing

See [`.github/CONTRIBUTING.md`](.github/CONTRIBUTING.md) — PSR-12, `declare(strict_types=1)`, file contract header required, PHPStan Level 6 gate.

**Adding a checker:** see [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md#adding-a-new-checker).

**Adding a Semgrep rule:** see [`rules/README.md`](rules/README.md#adding-a-new-rule).

---

## 📄 License

**Proprietary** — All rights reserved. See [`LICENSE`](LICENSE).

Internal use at [saturumah.net](https://saturumah.net). Not for redistribution without permission.

---

<div align="center">

**Built with ❤️ for PHP code that runs in production.**

[Report a bug](.github/ISSUE_TEMPLATE/bug_report.md) · [Request a feature](.github/ISSUE_TEMPLATE/feature_request.md) · [Security policy](.github/SECURITY.md)

</div>

## What's New — v2.4.4 (2026-10-28)

### Features

- **31 concrete checkers** (was 22): +DbGrowth, +Coverage, +Framework, +Ttfb, +A11y (deep crawl), +Complexity, +Duplication
- **13 Semgrep rules** (was 9): +ssti, +xxe-blind, +rce-blind, +info-disclosure
- **HTML report** — `--html[=PATH]` self-contained, dark/light auto
- **Framework detection** — WordPress, Laravel, Symfony, CI4, Yii, Cake, Drupal, Slim
- **Coverage** — pcov/xdebug-aware, activates when `phpunit.xml` present
- **TTFB tracking** — baseline-backed with 30ms noise floor
- **Complexity + Duplication** — graceful SKIP until phploc/phpcpd installed

### Flags

- `--html[=PATH]` — write self-contained HTML report
- `--version` — print version + PHP version
- `--help` — usage text
- `--section=NAME` — run one section
- `--ignore-path=PREFIX` — skip path prefix (repeatable)
- `--report[=PATH]` — write markdown report
- `--ci` — exit 1 on FAIL
- `--json` — machine-readable output

### Fixed (retroactive for all target audits)

- **StatsChecker** vendor exclude — 1947→31 files, -98.3% LOC inflation
- **ConfigChecker** tests/ exclude — no false "Missing CMS feature" WARNs
- **PhpUnitChecker** JUnit XML — PHPUnit 10+ compatibility
- **DuplicationChecker** LOC parse — direct % from phpcpd summary
- **TtfbChecker** noise floor — 30ms absolute threshold
- **A11yChecker** CHROME_BIN passthrough + raw output logging

