<!--
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : .github/PULL_REQUEST_TEMPLATE.md
 * │  Purpose   : Pull request template
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

## 📝 Description

<!-- What does this PR do? Why is it needed? -->

## 🔗 Related Issue

Fixes #<!-- issue number -->

## 🧪 Type of Change

- [ ] 🐛 Bug fix (non-breaking change)
- [ ] ✨ New feature (non-breaking change)
- [ ] 💥 Breaking change
- [ ] 📚 Documentation update
- [ ] 🔒 Security fix
- [ ] ♻️ Refactor

## ✅ Checklist

- [ ] **File contract header** added to all new/modified files
- [ ] `php -l` passes on all modified PHP files
- [ ] `composer dump-autoload --optimize` succeeds
- [ ] `./bin/forensic --root=. --show-findings` runs without error
- [ ] `CHANGELOG.md` updated under `[Unreleased]`
- [ ] README updated (if new checker added)
- [ ] Tests added (if applicable)
- [ ] **Read-only guarantee** verified (`--section=integrity` passes)

## 🧪 Test Evidence

```bash
# Paste output of:
./bin/forensic --root=/path/to/test-project --show-findings
```

## 📸 Screenshots (if UI-related)

<!-- Add screenshots for visual changes. -->

## 📋 Additional Notes

<!-- Anything else the reviewer should know. -->
