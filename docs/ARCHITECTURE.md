<!--
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : docs/ARCHITECTURE.md
 * │  Purpose   : Architecture, design decisions, extending the tool
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

# 🏗️ Architecture

Design decisions, module boundaries, and how to extend Bona Forensic.

## 📐 High-Level Overview

```
bin/forensic              ← CLI entry point (executable, shebang + declare)
   │
   ├── new Config(targetDir, showFindings)
   │     └── toolDir = realpath(__DIR__/../..)   ← hardcoded to tool dir
   │
   ├── new <Checker>($config) × 24             ← in $checkers array
   ├── IntegrityChecker special-case           ← --section=integrity branch
   └── SnapshotChecker (end)                   ← diff vs .bona/snapshots/
          │
          └── results[] = {stessage, details}
                └── summary: 0 FAIL / 0 WARN / 0 PASS
```

**Key invariant:** every checker returns `array` of `{status, message, details}`. `bin/forensic` aggregates and exits with code `0` (no FAIL) or `1` (FAIL present).

## 🧩 Checker Pattern

### Interface (contract)

```php
// src/Checkers/CheckerInterface.php
interface CheckerInterface {
    public function getName(): string;   // display name (used by --section= filter)
    public function run(): array;        // returns array of results
}
```

### Abstract base (shared state)

```php
// src/Checkers/AbstractChecker.php
abstract class AbstractChecker implements CheckerInterface {
    protected Config $config;
    protected array $results = [];

    public function __construct(Config $config) { $this->config = $config; }

    protected function addResult(string $status, string $message, array $details = []): void {
        $this->results[] = ['status' => $status, 'message' => $message, 'details' => $details];
    }
}
```

**Status values:** `PASS`, `WARN`, `FAIL`, `SKIP`, `INFO`.

- `PASS` — clean
- `WARN` — informational signal, not a gate (WARN ≠ exit 1)
- `FAIL` — actionable issue (drives exit code `1`)
- `SKIP` — checker gracefully declined (missing tool, no target files)
- `INFO` — count / metric without judgement

### Result details schema

```php
$this->addResult('FAIL', '17 broken require/include target(s)', [
    'list' => [
        'scripts/test_a.php:21 → config.php',
        'scripts/test_b.php:24 → includes/db.php',
        // ... (max 20 shown, then "... and N more")
    ],
]);
```

`bin/forensic` renders `details.list` in `--show-findings` mode, one item per line.

## 🔒 Read-Only Design (core guarantee)

### Path separation

```php
class Config {
    private string $targetDir;   // read-only (never used for writes)
    private string $toolDir;     // realpath(__DIR__/../..) — logs, snapshots, baselines

    public function getTargetDir(): string { return $this->targetDir; }
    public fn getToolDir(): string   { return $this->toolDir; }
}
```

**Rule:** every write goes to `$this->config->getToolDir() . '/.bona/...'`. Never `targetDir`.

- Snapshots: `toolDir/.bona/snapshots/<hash>.json`
- Baselines (v2.2+): `toolDir/.bona/ttfb-baseline.json`, `bundle-baseline.json`
- Semgrep cache, log output: `toolDir/.bona/logs/`

### IntegrityChecker — evidence

```
snapshotMD5(target)   → hash set S1
runAllCheckers()      → no writes (guaranteed by path separation)
snapshotMD5(target)   → has
if S1 != S2           → FAIL (target modified — bug)
else                  → PASS (proof)
```

`IntegrityChecker` is the **only** checker allowed to FAIL when "nothing happened." It is the audit of the audit.

### DatabaseChecker — READ-ONLY PDO

```php
$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_TIMEOUT                 => 3,
    PDO::MYSQL_ATTR_INIT_COMMAND      => "SET SESSION TRANSACTION READ ONLY",
]);
// Only SELECT / SHOW / PRAGMA / DESCRIBE / EXPLAIN
```

**Never** `INSERT` / `UPDATE` / `DELETE` / `ALTER` / `DROP`. `SET SESSION TRANSACTION READ ONLY` enforces this at MySQL level — even a bug cannot mutate.

## 🔍 Adding a New Checker

### Step 1 — Create the class

```php
// src/Checkers/MyNewChecker.php
<?php
/** SATURUMAH.NET · FILE CONTRACT header */
declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

use SatuRumah\Forensic\Core\FileSystem;

class MyNewChecker extends AbstractChecker {
    public function getName(): string { return 'My New Check'; }

    public function run(): array {
        $files = FileSystem::scanDir($this->config->getTargetDir(), ['php']);
        if (empty($files)) {
            $this->addResult('SKIP', 'No PHP files found.');
            return $this->results;
        }
        // ... analysis ...
        $this->addResult('PASS', sprintf('%d files analyzed.', count($files)));
        return $this->results;
    }
}
```

### Step 2 — Register in `bin/forensic`

**Two edits** (both required):

```php
// 1) Add use statement (grouped with other SatuRumah\Forensic\Checkers uses)
use SatuRumah\Forensic\Checkers\MyNewChecker;

// 2) Add to $checkers array (position determines run order)
$checkers = [
    // ...existing...
    new MyNewChecker($config),
    // ...existing...
];
```

### Step 3 — File contract header

Every `.php` file requires the SATURUMAH.NET header. Copy from an existing checker. Structure:

```php
<?php
/**
 * ┌──────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT │
 * │  ... full header ... │
 * └──────┘
 *
 * @author    Jack <Jack[at]SatuRumah[dot]Net>
 * @created   2026-10-26
 * @package   SatuRumah\Forensic\Checkers
 * @license   Proprietary
 */

declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;
```

### Step 4 — Verify

```bash
php -l src/Checkers/MyNewChecker.php          # syntax
vendor/bin/phpstan analyse --no-progress       # Level 6 gate — MUST pass
./bin/forensic --root=. --ci                    # self-audit — MUST stay 0 FAIL
.ensic --root=/path/to/target --section=my-new --show-findings   # real target
```

### Step 5 — Update docs

- `README.md` — add row to the appropriate table in "The 26 Checkers"
- `CHANGELOG.md` — add `- **MyNewChecker** — ...` under `[Unreleased]` → `### Added`
- `phpstan.neon` — add narrow `ignoreErrors` only if needed (prefer fixing)

## 🎨 Design Principles

### 1. Read-only is non-negotiable

Any checker that needs to write → `SKIP` with clear message. Never break the guarantee.

### 2. Context over pattern

Regex-only detection produces false positives (see `LESSONS-LEARNED.md` 05–07). Prefer:
- **File-level checks** over line-level (e.g. `escapeshellarg` presence in whole file, not just same line as `exec()`)
- **Semantic context** (introspection vs user query) over pattern match
- **AST (Semgrep)** over regex when possible

### 3. Graceful degradation

Every checker must `SKIP` (not crash) when:
- Required tool missing (`semgrep`, `pa11y`, `composer`)
- Target lacks required files (hpunit.xml`, `composer.json`, `config.php`)
- External service unreachable (DB, HTTP server)

### 4. WARN ≠ FAIL

`WARN` is informational. `FAIL` drives exit code. Choose carefully:

- **FAIL** — real risk (broken require, SQLi, missing security header on prod)
- **WARN** — optimization opportunity (many CSS files, large assets, missing OG tags)

WARN on self-audit is **expected** (test gap, `session.upload_progress`). FAIL on self-audit means the tool has a bug.

### 5. Self-audit is the quality gateash
./bin/forensic --root=. --ci    # MUST be 0 FAIL
```

Run before every commit. Also enforced in CI (`.github/workflows/bona-forensic.yml`, PHP 8.2 / 8.3 / 8.4 matrix).

## 🗂️ FileSystem helper

```php
// src/Core/FileSystem.php
public static function scanDir(string $root, array $exts, array $excludeDirs = []): array
```

Recursively walks `$root` for files matching `$exts`, skipping `$excludeDirs` (default: `vendor`, `node_modules`, `.git`, `cache`). Returns absolute paths.

**Never** write. **Only** `FilesystemIterator` + `RecursiveCallbackFilterIterator`.

## 🧪 Testing a Checker

No unit test harness yet (v2.3+ planned `CoverageChecker`). Current approach:

```bash
# Fast iteration (2-3s per section)
./bin/forensic --root=. --section=<name> --show-findings

# Verify against real target
./bin/forensic --root=~/saturumah/Bona-CMS-Dev-X --section=<name> --show-findings

# Full regression (30-120s)
./bin/forensic --root=. --ci
./bin/forensic --root=~/saturumah/Bona-CMS-Dev-X --show-findings
```

**Rule:** every new checker MUST be verified on both the tool itself (self-audit clean) and a real target (expected findings, no crash).

## 🔗 CI Matrix

`.github/workflows/bona-forensic.yml`:

- PHP `8.2` / `8.3` / `8.4`
- `composer install --no-dev --prefer-dist`
- `chmod +x bin/forensic`
- `./bin/forensic --root=. --ci --show-findings` → MUST exit 0

`.github/workflows/phpstan.yml`:

- PHPStan Level 6 → SARIF → upload to GitHub Security tab

**No CodeQL** — CodeQL doesn'PHP first-party. Replaced by PHPStan+SARIF in v2.0 (see `LESSONS-LEARNED.md` Lesson 08).

## 📚 Related Documents

- [`README.md`](../README.md) — install, CLI reference, checker catalog
- [`rules/README.md`](../rules/README.md) — Semgrep rule catalog & add-guide
- [`docs/REQUIREMENTS.md`](REQUIREMENTS.md) — platform / extension requirements
- [`LESSONS-LEARNED.md`](https://github.com/setuju/bona-cms-forensic) — 19 lessons (subprocess isolation, visible output, false-positive avoidance)
