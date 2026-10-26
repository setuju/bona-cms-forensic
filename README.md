<!--
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : README.md
 * │  Purpose   : Project overview, features, quick start, links
 * │  Author    : Jack — Jack[at]SatuRumah[dot]Net                        │
 * │  GitHub    : https://github.com/setuju                               │
 * │  Website   : https://saturumah.net                                   │
 * │  Created   : 2026-10-26                                              │
 * │  License   : Proprietary — all rights reserved                       │
 * └──────────────────────────────────────────────────────────────────────┘
 *
 * @author    Jack <Jack[at]SatuRumah[dot]Net>
 * @created   2026-10-26
 * @package   SatuRumah\Forensic\Docs
 * @license   Proprietary
 */
-->

<h1 align="center">🔬 Bona Forensic</h1>

<p align="center">
  <b>Enterprise-grade forensic audit tool for PHP applications</b><br>
  <i>18 modular checkers · Read-only · Local-only · CI/CD ready</i>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.4.27+-777BB4?logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/license-Proprietary-red" alt="License">
  <img src="https://img.shields.io/badge/checkers-18-blue" alt="Checkers">
  <img src="https://img.shields.io/badge/read--only-verified-green" alt="Read-only">
</p>

---

## 📖 Table of Contents

- [Why Bona Forensic?](#-why-bona-forensic)
- [Features](#-features)
- [Quick Start](#-quick-start)
- [Requirements](#-requirements)
- [The 20 Checkers](#-the-18-checkers)
- [CLI Reference](#-cli-reference)
- [Read-Only Guarantee](#-read-only-guarantee)
- [CI/CD Integration](#-cicd-integration)
- [Troubleshooting](#-troubleshooting)
- [Contributing &amp; License](#-contributing--license)

---

## 🎯 Why Bona Forensic?

Traditional PHP audit tools either:
- **Depend on SaaS** (Snyk, SonarCloud) — code leaves your machine
- **Require cloud** (Semgrep Cloud, GHAS) — privacy risk
- **Only do one thing** (PHPStan = types, Semgrep = SAST, PHPUnit = tests)

**Bona Forensic** combines **18 checks** into **one offline binary**:
- ✅ **Never modifies your target project** (MD5 hash before/after audit)
- ✅ **Never phones home** (zero telemetry)
- ✅ **Runs in ~6 seconds** (500 files, full audit)
- ✅ **Works in CI/CD** with exit-code gating

---

## ✨ Features

| Category | Capability |
|---|---|
| **Code Quality** | LOC stats, mean/median/outliers, PHP lint, tech debt (TODO/FIXME), class/function structure |
| **Dependency Graph** | Require/include graph, Mermaid macro (folder-to-folder), circular (DFS), import depth (BFS), centrality &amp; God Files |
| **Security Heuristics** | OWASP Top 10 (SQLi, XSS, RCE, LFI, CSRF, XXE, SSRF, deserialization, weak hash, `SELECT *`, unprepared queries) |
| **Runtime Checks** | Live HTTP server (random port), security headers (CSP, HSTS, X-Frame-Options), cookie flags, endpoint smoke tests |
| **Environment** | `php.ini` audit, PHP EOL check, **PHP2Shell CVE-2026-109618/109619** detection, CMS features (MFA, CSRF, password_hash) |
| **Database** | READ-ONLY schema analysis, missing PKs, text/blob columns, **SEO DB audit** (`settings.seo_*`, `monologues.seo_meta_*`) |
| **SEO** | Comprehensive: title, description, canonical, OG, Twitter, JSON-LD, H1, img alt, viewport, lang, noindex per page category |
| **Performance** | CLS prevention (img/iframe dims), page speed hints |
| **Integration** | Semgrep SAST (custom YAML), PHPUnit test suite (read-only), Log analysis |
| **Integrity** | Snapshot &amp; diff across runs, MD5 hash verification |

---

## 🚀 Quick Start

```bash
# 1. Clone &amp; install
git clone https://github.com/setuju/bona-cms-forensic.git
cd bona-cms-forensic
composer install

# 2. Full audit
./bin/forensic --root=/path/to/your-project --show-findings

# 3. CI/CD mode (exit 1 on failures)
./bin/forensic --root=/path/to/your-project --ci
```

**Sample output:**
```
╔══════════════════════════════════════════════════════════════════════╗
║     BONA FORENSIC — Enterprise Audit (Modular Architecture)          ║
╚══════════════════════════════════════════════════════════════════════╝
  Target : ../Bona-CMS-Dev-X
  Mode   : FULL FINDINGS

▶ Running: Code Statistics
  ✓ Files: 161 | Total LOC: 34,848 | Mean LOC: 216.45

▶ Running: Security Heuristics
  ✓ No critical security issues (XSS/SQLi/RCE/LFI).

▶ Running: Database Health &amp; SQL Injection Surface
  ✓ Database connection successful (READ-ONLY mode).
  ⚠ SEO settings KOSONG (3):
      - settings.seo_og_image_url = (EMPTY)

▶ Running: Read-Only Integrity Check
  ✓ Target directory TIDAK BERUBAH. 461 files verified read-only.
```

---

## 📦 Requirements

See **[docs/REQUIREMENTS.md](docs/REQUIREMENTS.md)** for the complete list.

**TL;DR:**
- PHP **8.4.27+** (or 8.3.36+, 8.5.12+)
- Extensions: `curl`, `xml`, `json`, `mbstring`, `pdo_mysql`, `tokenizer`, `fileinfo`
- Tools: Composer, Semgrep (opt), gh CLI (opt)

---

## 🔍 The 18 Checkers

| # | Checker | Focus |
|---|---|---|
| 1 | **StatsChecker** | LOC, mean, median, outliers |
| 2 | **MaintenanceChecker** | PHP lint, tech debt, structure |
| 3 | **DependencyChecker** | Require/include graph, Mermaid macro |
| 4 | **CircularDependencyChecker** | Circular (DFS) |
| 5 | **ImportDepthChecker** | Import depth (BFS from entry) |
| 6 | **CentralityChecker** | Fan-in, God Files |
| 7 | **OrphanChecker** | Files without importers |
| 8 | **SecurityChecker** | OWASP Top 10, `SELECT *`, unprepared |
| 9 | **AdvancedSecurityChecker** | Runtime: headers, cookies, uploads, JWT |
| 10 | **ConfigChecker** | php.ini, CVE, CMS features |
| 11 | **DatabaseChecker** | READ-ONLY schema + SEO DB |
| 12 | **Php2ShellChecker** | CVE-2026-109618/109619 |
| 13 | **SemgrepChecker** | SAST with custom YAML |
| 14 | **PhpUnitChecker** | Test suite (read-only) |
| 15 | **SmokeTestChecker** | HTTP endpoint smoke test |
| 16 | **LogChecker** | `error.log` analysis |
| 17 | **SeoChecker** | 13-point SEO (dynamic-aware) |
| 18 | **ClsChecker** | CLS prevention |

Plus: **IntegrityChecker** (MD5 hash) and **SnapshotChecker** (diff).

---

## 🎛️ CLI Reference

```bash
./bin/forensic [OPTIONS]

OPTIONS:
  --root=PATH         Target directory (default: cwd)
  --show-findings     Show all findings (untruncated)
  --json              Output JSON (for dashboards)
  --section=NAME      Run only one checker
  --ci                Exit 1 on FAIL (CI/CD mode)
```

**Examples:**
```bash
./bin/forensic --root=/var/www --section=security --show-findings
./bin/forensic --root=. --section=php2shell
./bin/forensic --root=/var/www --section=integrity
./bin/forensic --root=/var/www --json > report.json
```

---

## 🛡️ Read-Only Guarantee

Bona Forensic **NEVER** modifies the target directory. Proven cryptographically:

1. `IntegrityChecker` computes **MD5** of every target file **before** audit
2. All 18 checkers run read-only
3. `IntegrityChecker` re-computes **after** audit
4. If **any byte differs** → `CRITICAL: Target directory BERUBAH!` (exit 1)

**Modified (tool's own `.bona/`):**
- `.bona/logs/*.txt`, `.bona/snapshots/*.json`, `.bona/*.mmd`, `vendor/`

**Never modified (target):**
- Any `.php`, `.html`, `.js`, `.css`, `.json`, `.yaml`, `.sql`, `.htaccess`
- No `composer install` in target
- No writes to target's database (only `SELECT`)

---

## 🔗 CI/CD Integration

```yaml
name: Security Audit
on: [push, pull_request]

jobs:
  forensic:
    runs-on: ubuntu-24.04
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: curl, xml, mbstring, pdo_mysql, tokenizer, fileinfo

      - name: Install Bona Forensic
        run: |
          git clone https://github.com/setuju/bona-cms-forensic.git /tmp/forensic
          cd /tmp/forensic &amp;&amp; composer install --no-dev

      - name: Run Audit
        run: /tmp/forensic/bin/forensic --root=. --ci --show-findings

      - name: Upload Report
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: forensic-report
          path: /tmp/forensic/.bona/
```

---

## 🩺 Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| `Class not found` | Composer stale | `composer dump-autoload --optimize` |
| `cURL missing` | php-curl not installed | `sudo apt install php8.4-curl` |
| `DB connection failed` | Wrong credentials | Edit `config.php` in target |
| `GITHUB_TOKEN 403` | Env override | `env -u GITHUB_TOKEN -u GH_TOKEN git push` |
| `Integrity check FAIL` | Tool wrote to target | **Bug** — report issue |

---

## 🤝 Contributing &amp; License

- **Contributing**: [.github/CONTRIBUTING.md](.github/CONTRIBUTING.md)
- **Security**: [.github/SECURITY.md](.github/SECURITY.md)
- **Code of Conduct**: [.github/CODE_OF_CONDUCT.md](.github/CODE_OF_CONDUCT.md)
- **Changelog**: [CHANGELOG.md](CHANGELOG.md)

**License**: Proprietary — all rights reserved. © 2026 SatuRumah.Net

For commercial licensing: **Jack[at]SatuRumah[dot]Net**
