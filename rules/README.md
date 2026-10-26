<!--
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : rules/README.md
 * │  Purpose   : Semgrep custom rule catalog and authoring guide
 * │  Author    : Jack — Jack[at]SatuRumah[dot]Net                        │
 * │  GitHub    : https://github.com/setuju                               │
 * │  Website   : httumah.net                                   │
 * │  Created   : 2026-10-26                                              │
 * │  License   : Proprietary — all rights reserved                       │
 * └──────────────────────────────────────────────────────────────────────┘
 *
 * @author    Jack <Jack[at]SatuRumah[dot]Net>
 * @created   2026-10-26
 * @packmah\Forensic\Rules
 * @license   Proprietary
 */
-->

# 🧪 Semgrep Custom Rules

Bona Forensic ships **9 Semgrep rules** covering CWE Top 25 patterns. Rules are auto-discovered by `SemgrepChecker` — the checker runs `semgrep --config=rules/ .` against the target. **No PHP code changes needed** when adding or removing rules.

## 📋 Rule Catalog

| File | CWE | Severity | Sinks |
|---|---|---|---|
| `sqli.yaml` | CWE-89 | ERROR | `$DB->query("..." . $_GET)` |
| `xss.yaml` | CWE-79 | ERROR | `echo $_GET`, `print($_POST)`, `<?= $_COOKIE ?>` |
| `rce.yaml` | CWE-94 / CWE-78 | ERROR / WARNING | `eval`, `shell_exec` (with user input) |
| `php2shell.yaml` | CVE-2026-109618/109619 | ERROR | `session.upload_progress` + `memory_limit` patterns |
| `xxe.yaml` | CWE-611 | ERROR / WARNING | `libxml_disable_entity_loader(false)`, `LIBXML_NOENT` |
| `ssrf.yaml` | CWE-918 | ERROR / WARNING | `file_get_contents($_GET)`, `curl_setopt(..CURLOPT_URL..,$_POST)` |
| `deserialize.yaml` | CWE-502 | ERROR / WARNING | `unserialize($_POST)`, `yaml_parse` |
| `lfi.yaml` | CWE-98 | ERROR | `include($_GET)`, `require_once($_POST)` |
| `open-redirect.yaml` | CWE-601 | ERROR | `header("Location: ".$_GET)`, `wp_redirect($_GET)` |

**Total: 9 rules**, 13 distinct `id:` entries, covering OWASP A01 / A03 / A05 / A08 / A10.

## 🎯 Design — Zero False Positive Policy

**All sinks are superglobal-only** (`$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`). We do **not** match arbitrary `$var` patterns, because those produce false positive:

- config-driven values (`$baseUrl`, `$filterSql` from `match()` whitelist)
- inner-loop variables (`foreach` items)
- pre-sanitized values

**Why?** False positives cause alarm fatigue — developers ignore the tool. See **Lesson 20** in `LESSONS-LEARNED.md` (line-regex vs AST for structural pattern) and **Lesson 05–07** (context-aware whitelists in `SecurityChecker`).

### Example: `admin/quotes.php` — NOT flagged (correctly)

```php
$filter = $_GET['filter'] ?? 'all';
$filterSql = match ($filter) {
    'ai_drafts' => "WHERE ai_generated = 1 AND is_published = 0",
    'published' => "WHERE is_published = 1",
    'drafts'    => "WHERE is_published = 0",
    default     => "",
};
$quotes = $db->query("SELECT * FROM quotes {$filterSql} ORDER BY id DESC");
```

`$_GET` is a **selector key** for `match()` — SQL fragment is a hardcoded literal. This is **safe** and **not flagged** by `sqli.yaml` (which targets `".." . $_GET` concatenation, not `match()` whitelists).

## 🛠️ Adding a New Rule

### Step 1 — Create `rules/your-rule.yaml`

Copy the header block from an existing rule, then:

```yaml
rules:
  - id: php-your-rule-id
    patterns:
      - pattern-either:
          - pattern: some_function($_GET[...])
          - pattern: some_function($_POST[...])
    message: "Clear description — mention sink + risk"
    languages: [php]
    severity: ERROR   # or WARNING — ERROR = FAIL, WARNING = WARN
    metadata:
      cwe: "CWE-NNN: Name"
      owasp: "A0X:2021 - Category"
```

### Step 2 — Validate

```bash
semgrep --validate --config=rules/your-rule.yaml
# → Configuration is valid
```

### Step 3 — Test against your target

```bash
./bin/forensic --root=/path/to/target --section=semgrep --show-findings
```

### Step 4 — Test against the tool itself (self-audit)

```bash
./bin/forensic --root=. --ci
# → must stay 0 FAIL
```

**If a new rule produces a finding on the tool's own code**, that is a false positive — refine the rule (add `pattern-not` filters) or skip that pattern.

### Step 5 — Update this catalog

Add a row to the table above, and a `- **XxxRule**` line in `CHANGELOG.md` under `[Unreleased]` → `### Added`.

## 📚 Semgrep Syntax Cheat Sheet

| Construct | Purpose |
|---|---|
| `pattern: ...` | Single AST node match (preferred) |
| `pattern-either:` | OR — list of alternatives |
| `patterns:` | AND — list of conditions (all must match) |
| `pattern-not:` | Exclude if matches (negation) |
| `pattern-inside:` | Match only inside enclosing (e.g. `function foo(...) { ... }`) |
| `pattern-regex: '...'` | Line-based regex (last resort — use only for `header("Location:")` style cases where AST parsing fails) |
| `metavariable-pattern:` | Regex or pattern applied to a `$VAR` capture |
| `$X`, `$Y` | Metavariables — capture any expression |
| `...` | Ellipsis — "anything here" wildcard |

**Rule of thumb:** if `pattern:` works, use it. `pattern-regex` only when the pattern contains a token sequence Semgrep's PHP parser cannot represent (e.g. ` — concat + ellipsis).

## ⚠️ Known Deferred Rule

**`csrf.yaml`** — CSRF token presence detection. **Deferred to v2.4.0** as AST-based dedicated checker.

**Why:** initial line-based regex matched **all** `<form method="POST">` tags — **61 false positives** on a real target (every POST form flagged regardless of whether it contains `csrf_token`). Line-based regex cannot distinguish "form has token" from "form lacks token" reliably. Deferred to v2.4.0 AST-based approach.

**Interim coverage:** `AdvancedSecurityChecker` performs runtime CSRF surface checks (cookie flags, SameSite, etc.).
