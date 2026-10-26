<!--
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : .github/CONTRIBUTING.md
 * │  Purpose   : Contribution guidelines and code style rules
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

# 🤝 Contributing to Bona Forensic

Thank you for considering contributing! This document outlines the process.

## Code of Conduct

By participating, you agree to abide by our [Code of Conduct](CODE_OF_CONDUCT.md).

## How Can I Contribute?

### 🐛 Reporting Bugs

Open an issue using the **Bug Report** template. Include:
- Bona Forensic version (`git rev-parse HEAD`)
- PHP version (`php -v`)
- Target project details (framework, LOC)
- Full output (use `--show-findings`)
- Steps to reproduce

### 💡 Suggesting Features

Open an issue using the **Feature Request** template. Explain:
- What the feature solves
- Proposed implementation (if any)
- Alternatives considered

### 🔧 Submitting Pull Requests

1. Fork the repo and create a branch:
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Every new file MUST have the file contract header** — see existing files for examples. PRs without contracts will be rejected.

3. Run the test suite:
   ```bash
   php -l src/Checkers/YourNewChecker.php
   composer dump-autoload --optimize
   ./bin/forensic --root=. --show-findings
   ```

4. Follow coding standards:
   - **PSR-12** compliance (`declare(strict_types=1);` at top of every file)
   - **PSR-4** autoloading (`SatuRumah\Forensic\`)
   - Every checker extends `AbstractChecker` and implements `CheckerInterface`
   - Every check returns `addResult('PASS'|'FAIL'|'WARN'|'SKIP'|'INFO', 'message')`

5. Update `CHANGELOG.md` under `[Unreleased]`.

6. Commit using [Conventional Commits](https://www.conventionalcommits.org/):
   - `feat:` new feature
   - `fix:` bug fix
   - `docs:` documentation
   - `chore:` maintenance
   - `security:` security fix

7. Open a Pull Request using the template.

## Development Setup

```bash
git clone https://github.com/setuju/bona-cms-forensic.git
cd bona-cms-forensic
composer install
./bin/forensic --root=. --show-findings
```

## Adding a New Checker

1. Create `src/Checkers/YourChecker.php` with the file contract header
2. Extend `AbstractChecker`
3. Register in `bin/forensic` (`$checkers = [new YourChecker($config), ...]`)
4. Add to `src/Core/Config.php` if config needed
5. Update README "The 18 Checkers" table
6. Add to `CHANGELOG.md`

Example skeleton:

```php
<?php
declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

class YourChecker extends AbstractChecker {
    public function getName(): string { return 'Your Checker Name'; }
    public function run(): array {
        // ... your logic
        $this->addResult('PASS', 'No issues found.');
        return $this->results;
    }
}
```

## Style Guide

| Rule | Example |
|---|---|
| Strict types | `declare(strict_types=1);` at top |
| Namespace | `SatuRumah\Forensic\Checkers` |
| File contract | Required on **every** file |
| Method naming | `camelCase` |
| Constant naming | `SCREAMING_SNAKE_CASE` |
| Indentation | 4 spaces (no tabs) |
| Max line length | 120 chars |
| Docblocks | Required on all public methods |

## Recognition

Contributors are listed in `CHANGELOG.md` and on the GitHub repository page.
