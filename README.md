# 🔬 Bona Forensic — Enterprise CMS Audit Tool

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
 * └──────────────────────────────────────────────────────────────────────┘
 */
-->

> **Modular Forensic Audit Tool** untuk Bona CMS (atau CMS berbasis PHP lainnya).
> Menggunakan arsitektur PSR-4, Dependency Injection, dan 11 modular checkers.
> Semua pengecekan bersifat **Read-Only** (tidak ada aksi destruktif).

---

## 🚀 Persyaratan (Requirements)
- PHP 8.1+ (dengan ekstensi `curl` dan `tokenizer`)
- Composer

## 📦 Instalasi
```bash
git clone https://github.com/setuju/bona-cms-forensic.git
cd bona-cms-forensic
composer install
chmod +x bin/forensic
```

## 🎯 Cara Penggunaan

```bash
# Audit penuh (Human-readable)
./bin/forensic --root=/path/ke/project-cms

# Audit dengan menampilkan detail temuan (snippet/log)
./bin/forensic --root=/path/ke/project-cms --show-findings

# CI/CD Mode (Exit 1 jika ada FAIL)
./bin/forensic --root=. --ci
```

## 🔍 Modul Checker (Enterprise Architecture)

1. **StatsChecker**: Analisis LOC, Mean, Outlier.
2. **MaintenanceChecker**: PHP Lint, Tech Debt (TODO/FIXME), Struktur (Class/Function).
3. **DependencyChecker**: Peta dependensi & *Macro Mermaid Graph* (Folder-to-Folder).
4. **CircularDependencyChecker**: Deteksi siklus saling ketergantungan (DFS).
5. **OrphanChecker**: Deteksi file mati/yatim (tidak dipanggil).
6. **SecurityChecker**: Heuristik OWASP Top 10 (XSS, SQLi, RCE, LFI).
7. **AdvancedSecurityChecker**: Runtime Headers, Cookie, File Upload, CORS.
8. **ConfigChecker**: `php.ini` audit, CVE (Composer), Fitur CMS (MFA, CSRF).
9. **LogChecker**: Analisis file `error.log`.
10. **SeoChecker**: Validasi `<title>`, `<meta>`, dll.
11. **ClsChecker**: Pencegahan Layout Shift (dimensi `<img>`).

## 📊 Output & Log
- **Terminal**: Laporan berwarna (PASS, WARN, FAIL).
- **Mermaid**: File `.bona/dependency-graph.mmd` (bisa dibuka di [Mermaid Live Editor](https://mermaid.live/)).
- **Logs**: File `.bona/logs/log-*.txt` berisi detail temuan (Orphan, Tech Debt, SEO, dll).

## 🛡️ CI/CD (GitHub Actions)
Workflow `.github/workflows/bona-forensic.yml` sudah dikonfigurasi untuk menjalankan audit otomatis pada setiap Pull Request.

## 📄 Lisensi
Proprietary — all rights reserved. © 2026 SatuRumah.Net
