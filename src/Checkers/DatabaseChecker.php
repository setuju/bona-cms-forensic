<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Checkers/DatabaseChecker.php
 * │  Purpose   : Database schema analyzer and SQL injection surface detector
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
 * @package   SatuRumah\Forensic\Checkers
 * @license   Proprietary
 */

declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

use SatuRumah\Forensic\Core\FileSystem;

class DatabaseChecker extends AbstractChecker {
    public function getName(): string { return 'Database Health & SQL Injection Surface'; }

    public function run(): array {
        $creds = $this->findCredentials();
        if ($creds === null) {
            $this->addResult('SKIP', 'No database credentials found in config files.');
            return $this->results;
        }
        $this->addResult('INFO', "Config found: {$creds['driver']}://{$creds['host']}/{$creds['database']} (user: {$creds['user']})");
        try {
            $pdo = $this->connect($creds);
            $this->addResult('PASS', 'Database connection successful (READ-ONLY mode).');
            $this->analyzeSchema($pdo, $creds['driver']);
            $this->analyzeSeoConfig($pdo, $creds['driver']);
        } catch (\Exception $e) {
            $this->addResult('SKIP', 'Connection failed: ' . substr($e->getMessage(), 0, 100));
        }
        return $this->results;
    }

    private function findCredentials(): ?array {
        $configFiles = ['config.php', 'config.local.php', '.env', 'config/database.php', 'includes/db.php', 'includes/config.php'];
        foreach ($configFiles as $file) {
            $path = $this->config->getTargetDir() . '/' . $file;
            if (!file_exists($path)) continue;
            $content = @file_get_contents($path); if ($content === false) continue;

            // Pola 1: define('DB_HOST', 'x');
            if (preg_match("/define\(\s*['\"]DB_HOST['\"]\s*,\s*['\"]([^'\"]+)['\"]\)/", $content, $host) &&
                preg_match("/define\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]+)['\"]\)/", $content, $db) &&
                preg_match("/define\(\s*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]+)['\"]\)/", $content, $user)) {
                $pass = '';
                if (preg_match("/define\(\s*['\"]DB_PASS(?:WORD)?['\"]\s*,\s*['\"]([^'\"]*)['\"]\)/", $content, $p)) $pass = $p[1];
                return ['driver' => 'mysql', 'host' => $host[1], 'database' => $db[1], 'user' => $user[1], 'pass' => $pass];
            }
            // Pola 2: Array ['host' => 'x']
            if (preg_match("/(?:db_host|host)['\"]?\s*(?:=>|=)\s*['\"]([^'\"]+)['\"]/", $content, $host) &&
                preg_match("/(?:db_name|dbname|database)['\"]?\s*(?:=>|=)\s*['\"]([^'\"]+)['\"]/", $content, $db) &&
                preg_match("/(?:db_user|username|user)['\"]?\s*(?:=>|=)\s*['\"]([^'\"]+)['\"]/", $content, $user)) {
                $pass = '';
                if (preg_match("/(?:db_pass|password|pass)['\"]?\s*(?:=>|=)\s*['\"]([^'\"]*)['\"]/", $content, $p)) $pass = $p[1];
                return ['driver' => 'mysql', 'host' => $host[1], 'database' => $db[1], 'user' => $user[1], 'pass' => $pass];
            }
            // Pola 3: .env
            if (preg_match('/DB_HOST=([^\s\r\n]+)/', $content, $host) &&
                preg_match('/DB_(?:DATABASE|NAME)=([^\s\r\n]+)/', $content, $db) &&
                preg_match('/DB_USER(?:NAME)?=([^\s\r\n]+)/', $content, $user)) {
                $pass = '';
                if (preg_match('/DB_PASSWORD=([^\s\r\n]*)/', $content, $p)) $pass = $p[1];
                return ['driver' => 'mysql', 'host' => $host[1], 'database' => $db[1], 'user' => $user[1], 'pass' => $pass];
            }
            // Pola 4: SQLite
            if (preg_match('/["\'](?:db_path|sqlite_path|database_path)["\']\s*=>\s*["\']([^"\']+\.(?:sqlite|db))["\']/i', $content, $sqlite)) {
                $sqlitePath = $sqlite[1];
                if (!str_starts_with($sqlitePath, '/')) $sqlitePath = $this->config->getTargetDir() . '/' . $sqlitePath;
                if (file_exists($sqlitePath)) return ['driver' => 'sqlite', 'path' => $sqlitePath];
            }
        }
        return null;
    }

    private function connect(array $creds): \PDO {
        $options = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 3];
        if ($creds['driver'] === 'sqlite') return new \PDO("sqlite:{$creds['path']}", null, null, $options);
        $dsn = "mysql:host={$creds['host']};dbname={$creds['database']};charset=utf8mb4";
        return new \PDO($dsn, $creds['user'], $creds['pass'], $options);
    }

    private function analyzeSchema(\PDO $pdo, string $driver): void {
        try {
            $tables = ($driver === 'mysql') ? $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN) : $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(\PDO::FETCH_COLUMN);
            $this->addResult('INFO', 'Tables found: ' . count($tables));

            $noPK = []; $dangerousCols = [];
            foreach ($tables as $table) {
                $cols = ($driver === 'mysql') ? $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll() : $pdo->query("PRAGMA table_info(`{$table}`)")->fetchAll();
                $hasPK = false;
                foreach ($cols as $col) {
                    $field = $col['Field'] ?? $col['name'] ?? '';
                    $type = strtoupper($col['Type'] ?? $col['type'] ?? '');
                    $isPK = ($driver === 'mysql') ? ($col['Key'] === 'PRI') : ((int)($col['pk'] ?? 0) === 1);
                    if ($isPK) $hasPK = true;
                    if ((str_contains($type, 'TEXT') || str_contains($type, 'BLOB') || str_contains($type, 'VARCHAR')) && preg_match('/(content|body|description|excerpt|message|bio|comment|data|input|text)/i', $field)) {
                        $dangerousCols[] = "{$table}.{$field} ({$type})";
                    }
                }
                if (!$hasPK) $noPK[] = $table;
            }
            if (!empty($noPK)) $this->addResult('WARN', count($noPK) . ' tables WITHOUT PRIMARY KEY (rentan UPDATE/DELETE massal):', ['list' => $noPK]);
            else $this->addResult('PASS', 'All tables have PRIMARY KEY.');
            if (!empty($dangerousCols)) $this->addResult('INFO', count($dangerousCols) . ' string/text columns (potential XSS/SQLi storage):', ['list' => $dangerousCols]);
        } catch (\Exception $e) { $this->addResult('WARN', 'Schema analysis failed: ' . substr($e->getMessage(), 0, 100)); }
    }


    private function analyzeSeoConfig(\PDO $pdo, string $driver): void {
        try {
            $tables = ($driver === 'mysql')
                ? $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN)
                : $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(\PDO::FETCH_COLUMN);

            $seoSettings = [];
            $emptySeoSettings = [];
            $contentSeoGaps = [];
            $contentSeoOk = 0;

            foreach ($tables as $table) {
                $cols = ($driver === 'mysql')
                    ? $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll()
                    : $pdo->query("PRAGMA table_info(`{$table}`)")->fetchAll();
                $colNames = array_column($cols, $driver === 'mysql' ? 'Field' : 'name');

                // Tabel settings/config
                if (preg_match('/(setting|config|option)/i', $table)) {
                    $keyCol = null; $valCol = null;
                    foreach ($colNames as $col) {
                        if (!$keyCol && preg_match('/^(setting_?key|^key$|^name$|^option_?name)$/i', $col)) $keyCol = $col;
                        if (!$valCol && preg_match('/^(setting_?value|^value$|^val$|^option_?value)$/i', $col)) $valCol = $col;
                    }
                    if ($keyCol && $valCol) {
                        try {
                            $stmt = $pdo->query("SELECT `{$keyCol}` as k, `{$valCol}` as v FROM `{$table}` WHERE `{$keyCol}` LIKE '%seo%' OR `{$keyCol}` LIKE '%meta%' OR `{$keyCol}` LIKE '%og_%' OR `{$keyCol}` LIKE '%analytics%'");
                            foreach ($stmt->fetchAll() as $row) {
                                $key = $row['k'];
                                $val = trim((string)$row['v']);
                                if ($val === '') {
                                    $emptySeoSettings[] = "{$table}.{$key} = (EMPTY)";
                                } else {
                                    $seoSettings[] = "{$table}.{$key} = " . substr($val, 0, 50);
                                }
                            }
                        } catch (\Exception $e) {}
                    }
                }

                // Tabel konten
                if (preg_match('/(page|post|monologue|article|content|product|catalog)/i', $table)) {
                    $seoCols = array_filter($colNames, function($c) {
                        return preg_match('/(^seo_|^meta_(title|description|image)|^og_|^twitter_|^canonical_)/i', $c);
                    });
                    foreach ($seoCols as $field) {
                        try {
                            $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN `{$field}` IS NULL OR `{$field}` = '' THEN 1 ELSE 0 END) as empty_count FROM `{$table}`");
                            $row = $stmt->fetch();
                            if ($row && (int)$row['total'] > 0) {
                                $empty = (int)$row['empty_count'];
                                $total = (int)$row['total'];
                                if ($empty > 0) {
                                    $contentSeoGaps[] = "{$table}.{$field}: {$empty}/{$total} rows kosong";
                                } else {
                                    $contentSeoOk++;
                                }
                            }
                        } catch (\Exception $e) {}
                    }
                }
            }

            if (!empty($seoSettings)) {
                $this->addResult('INFO', 'SEO settings di DB (' . count($seoSettings) . ' items):', ['list' => array_slice($seoSettings, 0, 15)]);
            } else {
                $this->addResult('INFO', 'Tidak ada SEO settings ditemukan di tabel settings/config.');
            }
            if (!empty($emptySeoSettings)) {
                $this->addResult('WARN', 'SEO settings KOSONG (' . count($emptySeoSettings) . '):', ['list' => array_slice($emptySeoSettings, 0, 15)]);
            }
            if (!empty($contentSeoGaps)) {
                $this->addResult('WARN', 'Content SEO fields kosong (' . count($contentSeoGaps) . ' kolom):', ['list' => array_slice($contentSeoGaps, 0, 15)]);
                FileSystem::writeLog($this->config->getLogDir(), 'seo-db-gaps', $contentSeoGaps);
            } elseif ($contentSeoOk > 0) {
                $this->addResult('PASS', 'Semua kolom SEO di tabel konten terisi (' . $contentSeoOk . ' kolom OK).');
            }
        } catch (\Exception $e) {
            $this->addResult('SKIP', 'SEO DB audit gagal: ' . substr($e->getMessage(), 0, 100));
        }
    }

}