<!--
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : .github/SECURITY.md
 * │  Purpose   : Security policy and vulnerability disclosure
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

# 🔒 Security Policy

## Supported Versions

| Version | PHP | Security Fixes |
|---|---|---|
| **2.0.x** | 8.1 – 8.4 | ✅ Active |
| 1.0.x | 8.1 – 8.3 | ⚠️ Legacy (upgrade recommended) |

## Reporting a Vulnerability

**DO NOT open a public GitHub issue for security vulnerabilities.**

Instead, email:
- 📧 **Jack[at]SatuRumah[dot]Net**
- 🔑 PGP key: [available on request]

Include:
1. Description of the vulnerability
2. Steps to reproduce
3. Affected versions
4. Potential impact

## Response Timeline

| Stage | Timeframe |
|---|---|
| Acknowledgment | &lt; 48 hours |
| Initial assessment | &lt; 5 business days |
| Fix release (critical) | &lt; 14 days |
| Public disclosure | After fix + 30 days |

## Bona Forensic Security Model

Bona Forensic is a **local, read-only forensic audit tool**. Its own attack surface is intentionally minimal:

### ✅ What We Protect Against

| Threat | Mitigation |
|---|---|
| **Target file modification** | MD5 hash before/after audit (`IntegrityChecker`) |
| **Credential leakage to target** | `Config::toolDir` hardcoded, cannot leak to target |
| **SQL injection in DB audit** | Uses parameterized queries OR hardcoded strings only |
| **Command injection via exec()** | All `exec()` calls use `escapeshellarg()` |
| **Telemetry exfiltration** | Zero network calls except optional `composer audit` |
| **Malicious config file** | Config file is parsed with regex, never `include`d |

### 🚫 Known Non-Issues (By Design)

| Concern | Reason |
|---|---|
| Tool runs target's PHP code | **It doesn't.** Read-only filesystem + optional DB `SELECT` only. |
| Tool uploads code to cloud | **It doesn't.** Semgrep runs locally; no cloud calls. |
| Tool needs write access to target | **It doesn't.** Verified via `IntegrityChecker`. |
| Tool has CVEs in dependencies | Run `composer audit`; we update quarterly. |

## PHP2Shell (CVE-2026-109618 / CVE-2026-109619)

Bona Forensic includes **detection** for php2shell. Recommended safe versions:
- PHP 8.2.x: **8.2.35+**
- PHP 8.3.x: **8.3.36+**
- PHP 8.4.x: **8.4.27+**
- PHP 8.5.x: **8.5.12+**

The tool's own runtime must also be safe. Our CI runs on the latest 8.4.

## Compliance

- ✅ **No personal data** processed
- ✅ **No PII** stored in `.bona/logs/` (only paths, line numbers, code snippets)
- ✅ **No telemetry** sent anywhere
- ✅ **GDPR-friendly** (target data stays local)
