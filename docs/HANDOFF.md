<!--
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : docs/HANDOFF.md
 * │  Purpose   : Session handoff, state snapshot, mandatory rules
 * │  Author    : Jack — Jack[at]SatuRumah[dot]Net                        │
 * │  GitHub    : https://github.com/setuju                               │
 * │  Website   : https://saturumah.net                                   │
 * │  Created   : 2026-10-28                                              │
 * │  License   : Proprietary — all rights reserved                       │
 * └──────────────────────────────────────────────────────────────────────┘
 *
 * @author    Jack <Jack[at]SatuRumah[dot]Net>
 * @created   2026-10-28
 * @package   SatuRumah\Forensic
 * @license   Proprietary
 */
-->

# 🔄 Session Handoff — Bona Forensic

**Last updated:** 2026-10-28 12:43 UTC
**Pre-session HEAD:** 456f852 (before v2.2.0.2 retry commit)
**Repo:** `~/saturumah/bona-cms-forensic`

---

## 📊 Current State (verified — no assumptions)

| Item | Status |
|---|---|
| Self-audit | 0 FAIL, 2 WARN (session.upload_progress + test gap — both legit) |
| PHPStan L6 | 0 errors |
| Checkers | 31 concrete (22 primary + 9 utility: Integrity, Snapshot, Ttfb, A11y, Framework, DbGrowth, Coverage, Complexity, Duplication) |
| Semgrep rules | 13 (9 existing + 4 v2.5.0: ssti, xxe-blind, rce-blind, info-disclosure) |
| TtfbChecker | Real baseline-backed TTFB tracking |
| A11yChecker | Real WCAG 2.1 AA (root + deep crawl v2.2.0.2) |
| CI | Bona Forensic Self-Audit + PHPStan, matrix PHP 8.2/8.3/8.4 |

---

## 🎯 What Just Shipped

- **v2.2.0.2 retry** — A11yChecker deep crawl v2 (proper)
  - Fixed previous revert (e851836): rc=2 acceptance, server-ready poll, language filter, priority sort
  - Removed unused `writePa11yConfig()` method
- **v2.5.0 complete** — CSRF in SecurityChecker (METHOD A file-level + form-block)
- **v2.2.0.1** — A11yChecker root-only (baseline)
- **v2.2.5-A.2** — MaintenanceChecker batched lint (14% faster)
- **v2.2.5-D.1** — PASS dedup + smoke-report test

---

## 🚀 Next Priorities

| Priority | Task | Prereq |
|---|---|---|
| ~~P1~~ ✅ | v2.3.0 — DbGrowth + CoverageChecker | **DONE** — pcov 1.0.12 via `apt install php8.4-pcov` |
| P2 | v2.4.0 — ComplexityChecker, DuplicationChecker | composer: phpmetrics/phpcpd |
| P3 | v2.6.0 — Grafana dashboard, HTML report | none |
| P4 | v2.7.0 — Framework-specific (WordPress, Laravel, Symfony) | none |

---

## 🛑 HARD RULES (never violate)

1. **READ-ONLY targets mutlak**
   - `~/saturumah.net/public_html/` — production CMS, NEVER modify
   - `~/saturumah/Bona-CMS-Dev-X/` — test target, NEVER modify
   - **NEVER** generate fix scripts, patches, SQL, or README instructions for targets
   - Audit output only — findings are informational for user
   - MD5 verify before/after every target access

2. **NEVER touch other projects**
   - Any folder in `~/saturumah/` other than `bona-cms-forensic` is off-limits
   - If user pastes output from another project, ignore (do not analyze/act)

3. **Recon before action (Lesson 15)**
   - Read current code with `sed -n`, `grep`, `cat` BEFORE any patch
   - Verify anchor strings exist before replace
   - If patch fails "no match", dump context — do NOT try variants blindly

4. **Subprocess isolation (Lesson 18)**
   - `clear` as line 1 of every bubble
   - Wrap patch scripts in `cat > /tmp/x.sh <<'EOF' ... EOF; bash /tmp/x.sh`
   - `exit` inside subprocess does not kill WSL
   - Echo `[RC] 0` after subprocess — parent shell survives

5. **Visible output on verify gates (Lesson 19)**
   - NEVER `>/dev/null 2>&1` on gates (phpstan, self-audit, php -l)
   - Use `tee /tmp/log.txt` + `PIPESTATUS[0]` for exit code
   - Silent gate fails = blind debug = wasted bubble

6. **Exit-checked git ops (Lesson 15)**
   - Every `git add`/`commit`/`push` with `|| exit 2`
   - `if ! cmd; then echo "[ERR]"; exit 2; fi` — silent OK is wrong

7. **env -u GITHUB_TOKEN -u GH_TOKEN on push**
   - Push: `env -u GITHUB_TOKEN -u GH_TOKEN git push origin main`
   - Auth: `env -u GITHUB_TOKEN -u GH_TOKEN gh auth switch -u setuju`
   - Verify: `env -u GITHUB_TOKEN -u GH_TOKEN gh auth status`

8. **File contract header required**
   - Every `.php`/`.yaml`/`.md`/`.yml` MUST have SATURUMAH.NET header in first 15 lines
   - Verify via `head -15 file | grep -c SATURUMAH` after any new file
   - `bin/forensic`: shebang line 1, `<?php` line 2, header line 3, `declare` below

9. **No `input()` in Python**
   - Scripts must be non-interactive
   - Fail fast with `[ERR]` on missing anchors — do not prompt user

10. **Idempotence + backup**
    - Patch scripts must check "already patched" and skip (idempotent)
    - Always `cp src/file.php /tmp/backup.bak` before mutation
    - Restore on any gate failure

11. **Gate failures abort, not proceed**
    - If PHPStan fails → restore + exit 2 (do NOT commit)
    - If self-audit fails → restore + exit 2
    - If SKIP is due to technical failure (JSON parse, discovery fail) → NOT success
    - Only SKIP for "not applicable" (no entry point, tool missing) is real success

12. **Diagnose before re-fix**
    - If patch fails, dump full PHPStan/`php -l`/`git diff` output
    - Do NOT guess fixes
    - Use `--error-format=raw` for PHPStan to see exact lines

---

## 🔬 Mandatory Workflow

For every change:

1. **RECON** — read current code, verify anchor exists, list exact strings
2. **PATCH** — minimal change, idempotent, backup first, fail-fast on missing anchor
3. **VERIFY** — `php -l` → PHPStan L6 → self-audit 0 FAIL → functional test on target
4. **COMMIT** — exit-checked, descriptive message, env-bypass push
5. **CONFIRM** — report output to user before next step

---

## 📚 Lessons Learned Checklist (from LESSONS-LEARNED.md)

- **L05** — False positive RCE: file-level escapeshellarg check, not per-line
- **L06** — False positive SQLi: skip introspection queries (SHOW/PRAGMA/DESCRIBE)
- **L07** — False positive SEO: skip src/ (regex container files)
- **L08** — CodeQL doesn't support PHP → PHPStan + SARIF (already in CI)
- **L09** — env -u GITHUB_TOKEN mandatory (GITHUB_TOKEN overrides gh auth)
- **L13** — Read package README before assuming config
- **L15** — Recon before action; dump context on patch failure
- **L18** — Subprocess isolation (`clear` line 1, `cat /tmp/x.sh + bash`)
- **L19** — Visible output on gates, never `>/dev/null`

Additional learnings from this session:
- **rc=2 from pa11y-ci = "found issues"** (not error) — accept both 0 and 2
- **Server startup races** — poll with retry, do NOT fixed sleep
- **Silent skip in Python patch** — always `print("[SKIP]")` when `if X in c` fails
- **Bubble gate design** — SKIP due to technical failure ≠ SKIP due to not applicable
- **Multi-project paste** — if user pastes output from another cwd, ask before acting

---

## ⚠️ Known Issues / Blockers

- **saturumah.net root**: 301 → `http://localhost:8000/id/` (hardcoded base_url in app config).
  - A11y deep crawl will SKIP until fixed **app-side** (user responsibility, not tool bug)
  - Not blocking other checkers on that target

- ~~pcov/xdebug not installed~~ ✅ **RESOLVED 2026-10-28** — pcov 1.0.12 installed via `apt install php8.4-pcov`
  - CoverageChecker now active for targets with phpunit.xml (self-audit SKIPs correctly — no tests)

- **Bona WCAG errors**: 86 real findings on root (contrast, form submit)
  - These are TRUE findings for user to fix target-side — not tool bugs

---

## 📎 Files to Attach Next Session

Repo (in-git):
- `docs/HANDOFF.md` (this file)
- `docs/ARCHITECTURE.md`
- `docs/REQUIREMENTS.md`
- `src/Checkers/A11yChecker.php` (if working on A11y)
- `src/Checkers/TtfbChecker.php` (if working on TTFB)

External handoff (user provides):
- `~/saturumah/handoffs/handoffs-bona-cms-forensic/SESSION-HANDOFF.md`
- `~/saturumah/handoffs/handoffs-bona-cms-forensic/ROADMAP.md`
- `~/saturumah/handoffs/handoffs-bona-cms-forensic/LESSONS-LEARNED.md`

---

## 🧪 Testing Evidence (last run)

- Self-audit: 0 FAIL, 2 WARN ✅
- PHPStan L6: 0 errors ✅
- CI: green on last push (verify after v2.2.0.2 retry commit)
- Bona: A11y deep crawl runs to completion, MD5 unchanged
- Prod: A11y SKIP with clear reason (app base_url bug)

---

*End of handoff. Next session: read this file, run recon, do not assume.*
