# 🔬 Bona Forensic — Unified CMS Audit Script

<!--
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : README.md                                               │
 * │  Purpose   : Project documentation and usage guide                   │
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
-->

> **Single-file PHP forensic audit tool** for Bona CMS (or any PHP-based CMS).
> 27 sections covering statistics, dependency mapping, security (OWASP Top 10),
> SEO, Core Web Vitals, code quality, and CVE detection.
> 
> **All checks are READ-ONLY.** No destructive actions. No active exploitation.

---

## 🚀 Quick Start

```bash
# Full audit (human-readable)
php scripts/bona-forensic.php

# CI mode (exit 1 if critical issues found)
php scripts/bona-forensic.php --ci

# JSON output (for integration)
php scripts/bona-forensic.php --json > report.json

# Quick mode (skip tokenizer, fastest)
php scripts/bona-forensic.php --quick

# Orphans only
php scripts/bona-forensic.php --orphans-only

# Mermaid diagram
php scripts/bona-forensic.php --mermaid
```

## 🔍 27 Audit Sections

1. **Code Statistics** (LOC, Mean, Outliers)
2. **Log Analysis** (Error detection)
3. **Reliability** (Unit tests presence)
4. **Asset Performance** (Large files detection)
5. **Security Baseline** (.env exposure, hardcoded credentials)
6. **Database Health** (Schema, migrations)
7. **Dependency Graph** (Coupling analysis)
8. **Orphan Detection** (Unused files)
9. **Broken Links** (Missing includes)
10. **Snapshot & Diff** (Changes since last run)
11. **Circular Dependencies** (DFS cycle detection)
12. **Centrality / Hotspot** (God files detection)
13. **Import Depth** (BFS from entry points)
14. **Technical Debt** (TODO, FIXME, HACK)
15. **Structural Overview** (Class/Interface count)
16. **PHP Lint** (Syntax verification)
17. **Security Heuristics** (OWASP Top 10: SQLi, XSS, RCE, LFI/RFI, CSRF, XXE, SSRF)
18. **PHP Config Audit** (php.ini security flags)
19. **HTTP Security Headers** (CSP, HSTS, X-Frame-Options)
20. **SEO Static Analysis** (Meta tags, JSON-LD)
21. **CLS Prevention** (Missing img/iframe dimensions)
22. **Page Speed Hints** (Blocking scripts, CSS)
23. **CVE & Dependency Audit** (composer audit)
24. **CMS Security Features** (MFA, RBAC, Password Hashing)
25. **Cookie & Session Security** (HttpOnly, Secure, SameSite)
26. **File Upload Security** (.htaccess protection, MIME validation)
27. **API & CORS Security** (Wildcard CORS, JWT, Hardcoded keys)

## 🔗 CI/CD Integration (GitHub Actions)

```yaml
name: Bona Forensic Audit
on: [push, pull_request]
jobs:
  forensic:
    runs-on: ubuntu-24.04
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          tools: composer
      - run: php scripts/bona-forensic.php --ci
```

## ⚠️ Limitations & Notes

- **Read-only**: No files are modified. No exploits are executed.
- **Heuristics**: Regex-based detection has false positives. Manual review required.
- **SAST**: For deep analysis, use Semgrep/PHPStan alongside this script.
- **CVE**: `composer audit` requires internet. Works fine in CI.
- **SEO/CLS**: Static template checks only. Use Lighthouse CI for actual rendering.

## 🛡️ Defense in Depth

Bona Forensic is **Layer 1** (Early Warning).
- Layer 1: Bona Forensic (fast, local, 27 sections)
- Layer 2: Semgrep + Snyk + PHPStan (deep SAST + CVE)
- Layer 3: Lighthouse CI (real rendering, Core Web Vitals)
- Layer 4: Sentry/Bugsnag (runtime error monitoring)

## 📄 License

Proprietary — all rights reserved. © 2026 SatuRumah.Net
