<!--
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : docs/REQUIREMENTS.md
 * │  Purpose   : System requirements and installation guide
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
 * @package   SatuRumah\Forensic\Docs
 * @license   Proprietary
 */
-->

# 📋 System Requirements

> Complete requirements to run **Bona Forensic Enterprise v2.0** on Ubuntu 22.04/24.04, WSL2, or Debian 12.

---

## 1. PHP Runtime

| Requirement | Minimum | Recommended | Notes |
|---|---|---|---|
| **PHP** | 8.1 | **8.4.27+** | 8.3.36+ / 8.4.27+ / 8.5.12+ required to be safe from php2shell |

## 2. PHP Extensions

| Extension | Used by | Purpose |
|---|---|---|
| `php-curl` | AdvancedSecurityChecker, SmokeTestChecker | Runtime HTTP checks |
| `php-xml` / `php-simplexml` | PhpUnitChecker | Parse JUnit XML |
| `php-json` | All | Config &amp; report |
| `php-mbstring` | All | Safe string handling |
| `php-pdo` + `php-pdo_mysql` | DatabaseChecker | READ-ONLY DB |
| `php-tokenizer` | DependencyChecker, MaintenanceChecker | Parse require/include |
| `php-fileinfo` | AdvancedSecurityChecker | Magic-byte upload validation |
| `php-openssl` | Optional | HTTPS |
| `php-iconv` | Built-in | Encoding |

**Verify:**
```bash
php -r "foreach(['curl','xml','json','mbstring','pdo','pdo_mysql','tokenizer','fileinfo'] as \$e) echo \$e.': '.(extension_loaded(\$e)?'OK':'MISSING').PHP_EOL;"
```

## 3. CLI Tools

| Tool | Purpose | Install |
|---|---|---|
| **Composer 2.x** | PSR-4 + PHPUnit | `curl -sS https://getcomposer.org/installer \| php` |
| **Git 2.x** | Version control | `sudo apt install git` |
| **gh CLI** | GitHub push (optional) | `sudo apt install gh` |
| **Semgrep 1.100+** | SAST integration | `pipx install semgrep` |
| **pkill** (psmisc) | Kill PHP server | `sudo apt install psmisc` |
| **mysql client** | DB audit (optional) | `sudo apt install mysql-client` |

## 4. Optional Tools

| Tool | Purpose |
|---|---|
| `lighthouse` (Node.js) | Deep page-speed (planned) |
| `xdebug` / `pcov` | PHPUnit code coverage |
| `jq` | JSON parsing in shell |
| `mariadb` client | Alternative MySQL |

## 5. Database Support

READ-ONLY schema analysis supports:
- **MariaDB** 10.x / 11.x
- **MySQL** 8.x
- **SQLite** 3.x

Auto-parses credentials from: `config.php`, `config.local.php`, `.env`, `config/database.php`, `includes/db.php`.

Connection failure → **SKIP** (never fails audit).

## 6. Complete Install (Ubuntu / WSL2)

```bash
# --- PHP 8.4 via Sury PPA ---
sudo apt install -y ca-certificates curl apt-transport-https lsb-release gnupg
sudo curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg
echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/sury-php.list
sudo apt update
sudo apt install -y php8.4 php8.4-cli php8.4-common php8.4-curl php8.4-mbstring \
                    php8.4-xml php8.4-zip php8.4-mysql php8.4-fileinfo php8.4-tokenizer
sudo update-alternatives --set php /usr/bin/php8.4

# --- Composer ---
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# --- Semgrep via pipx ---
sudo apt install -y pipx psmisc
pipx install semgrep
pipx ensurepath

# --- gh CLI ---
sudo apt install -y gh
gh auth login
```

## 7. Verification

```bash
cd /path/to/bona-cms-forensic

# Check PHP extensions
php -r "foreach(['curl','xml','simplexml','json','mbstring','pdo','pdo_mysql','tokenizer','fileinfo'] as \$e) echo \$e.': '.(extension_loaded(\$e)?'OK':'MISSING').PHP_EOL;"

# Check tools
for t in composer semgrep git gh pkill; do which \$t || echo "\$t MISSING"; done

# Smoke test
./bin/forensic --root=. --section=stats
```

## 8. Known Limitations

| Constraint | Reason | Fix |
|---|---|---|
| php2shell (CVE-2026-109618/109619) | PHP <8.3.36, <8.4.27 | Upgrade PHP |
| cURL missing | Minimal WSL | `sudo apt install php8.4-curl` |
| PyYAML missing (PEP 668) | Ubuntu 24.04 | Use `semgrep --validate` |
| GITHUB_TOKEN 403 | Env override | `env -u GITHUB_TOKEN -u GH_TOKEN git push` |

## 9. Environment Variables

| Variable | Effect | Recommendation |
|---|---|---|
| `GITHUB_TOKEN` | Overrides gh CLI | **Unset** before git push |
| `GH_TOKEN` | Alias for above | **Unset** |
| `PHP_INI_SCAN_DIR` | Custom php.ini | Leave default |

## 10. Resource Estimate

| Metric | Value |
|---|---|
| RAM (500 files) | ~40 MB |
| Disk (tool + deps) | ~25 MB |
| Runtime (500 files) | ~6 s |
| Runtime (quick) | ~2 s |

## 11. Supported Targets

- ✅ **Bona CMS** (tested, 161 files)
- ✅ **WordPress** (partial)
- ✅ **Laravel** (partial)
- ✅ **CodeIgniter 3/4**
- ✅ **Slim Framework**

For unknown frameworks, run `--show-findings` first to verify no false positives.
