<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Checkers/DbGrowthChecker.php
 * │  Purpose   : DB table row-count growth tracking (READ-ONLY PDO)
 * │  Author    : Jack — Jack[at]SatuRumah[dot]Net                        │
 * │  GitHub    : https://github.com/setuju                               │
 * │  Website   : https://saturumah.net                                   │
 * │  Created   : 2026-10-28                                              │
 * │  License   : Proprietary — all rights reserved                       │
 * └──────────────────────────────────────────────────────────────────────┘
 *
 * @author    Jack <Jack[at]SatuRumah[dot]Net>
 * @created   2026-10-28
 * @package   SatuRumah\Forensic\Checkers
 * @license   Proprietary
 */

declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

class DbGrowthChecker extends AbstractChecker {
    public function getName(): string { return 'DB Growth Tracking'; }

    private const SNAPSHOT = 'db-snapshot.json';
    private const GROWTH_WARN = 50.0;   // % growth in one run → WARN
    private const CONNECT_TIMEOUT = 3;

    public function run(): array {
        $root = $this->config->getTargetDir();
        $creds = $this->detectCredentials($root);
        if ($creds === null) {
            $this->addResult('SKIP', 'No database credentials found in config files.');
            return $this->results;
        }

        $pdo = $this->tryConnect($creds);
        if ($pdo === null) {
            $this->addResult('SKIP', 'DB connection failed (creds found in config, but unreachable).');
            return $this->results;
        }

        try {
            $tables = $this->listTables($pdo, $creds['driver']);
            if (empty($tables)) {
                $this->addResult('SKIP', 'No tables found in database.');
                return $this->results;
            }

            $counts = [];
            foreach ($tables as $t) {
                $counts[$t] = $this->rowCount($pdo, $t, $creds['driver']);
            }

            $snapshotPath = $this->config->getToolDir() . '/.bona/' . self::SNAPSHOT;
            $prev = $this->loadSnapshot($snapshotPath);

            if ($prev === null) {
                $this->saveSnapshot($snapshotPath, $counts);
                $this->addResult('INFO', sprintf(
                    'DB baseline established: %d table%s, %d total rows.',
                    count($counts), count($counts) === 1 ? '' : 's', array_sum($counts)
                ));
                return $this->results;
            }

            $warns = [];
            foreach ($counts as $t => $cur) {
                $old = $prev['tables'][$t] ?? null;
                if ($old === null || $old === 0) continue;
                $growth = (($cur - $old) / $old) * 100;
                if ($growth > self::GROWTH_WARN) {
                    $warns[] = sprintf('%s: %d → %d (+%.0f%%)', $t, $old, $cur, $growth);
                }
            }

            $this->saveSnapshot($snapshotPath, $counts);

            if (!empty($warns)) {
                $this->addResult('WARN', sprintf(
                    '%d table%s grew >%.0f%% since last snapshot.',
                    count($warns), count($warns) === 1 ? '' : 's', self::GROWTH_WARN
                ), ['list' => $warns]);
            } else {
                $this->addResult('PASS', sprintf(
                    'DB growth normal: %d table%s tracked, no table >%.0f%% growth.',
                    count($counts), count($counts) === 1 ? '' : 's', self::GROWTH_WARN
                ));
            }
        } catch (\Throwable $e) {
            $this->addResult('SKIP', 'DB introspection error: ' . $e->getMessage());
        }

        return $this->results;
    }

    /** @return array{driver:string,dsn:string,user:string,pass:string}|null */
    private function detectCredentials(string $root): ?array {
        $candidates = ['config.php', 'config.local.php', 'includes/db.php', 'config/database.php'];
        foreach ($candidates as $rel) {
            $p = $root . '/' . $rel;
            if (!is_readable($p)) continue;
            $raw = @file_get_contents($p);
            if ($raw === false) continue;

            $host = $this->grab($raw, 'DB_HOST') ?? 'localhost';
            $name = $this->grab($raw, 'DB_NAME') ?? $this->grab($raw, 'DB_DATABASE');
            $user = $this->grab($raw, 'DB_USER') ?? $this->grab($raw, 'DB_USERNAME');
            $pass = $this->grab($raw, 'DB_PASS') ?? $this->grab($raw, 'DB_PASSWORD') ?? '';

            if ($name !== null && $user !== null) {
                return [
                    'driver' => 'mysql',
                    'dsn'    => "mysql:host={$host};dbname={$name};charset=utf8mb4",
                    'user'   => $user,
                    'pass'   => $pass,
                ];
            }
        }

        $env = $root . '/.env';
        if (is_readable($env)) {
            $raw = (string)@file_get_contents($env);
            $name = $this->grabEnv($raw, 'DB_DATABASE') ?? $this->grabEnv($raw, 'DB_NAME');
            $user = $this->grabEnv($raw, 'DB_USERNAME') ?? $this->grabEnv($raw, 'DB_USER');
            if ($name !== null && $user !== null) {
                $host = $this->grabEnv($raw, 'DB_HOST') ?? 'localhost';
                $pass = $this->grabEnv($raw, 'DB_PASSWORD') ?? '';
                return [
                    'driver' => 'mysql',
                    'dsn'    => "mysql:host={$host};dbname={$name};charset=utf8mb4",
                    'user'   => $user,
                    'pass'   => $pass,
                ];
            }
        }

        return null;
    }

    private function grab(string $src, string $key): ?string {
        if (preg_match('/define\s*\(\s*[\'"]' . preg_quote($key, '/') . '[\'"]\s*,\s*[\'"]([^\'"]*)[\'"]/', $src, $m)) return $m[1];
        if (preg_match('/[\'"]' . preg_quote($key, '/') . '[\'"]\s*=>\s*[\'"]([^\'"]*)[\'"]/', $src, $m)) return $m[1];
        return null;
    }

    private function grabEnv(string $src, string $key): ?string {
        if (preg_match('/^' . preg_quote($key, '/') . '\s*=\s*([^\r\n#]*)/m', $src, $m)) {
            $v = trim($m[1], " \t\"'");
            return $v !== '' ? $v : null;
        }
        return null;
    }

    private function tryConnect(array $creds): ?\PDO {
        try {
            return new \PDO($creds['dsn'], $creds['user'], $creds['pass'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => self::CONNECT_TIMEOUT,
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION TRANSACTION READ ONLY',
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @return list<string> */
    private function listTables(\PDO $pdo, string $driver): array {
        $tables = [];
        if ($driver === 'mysql') {
            $stmt = $pdo->query('SHOW TABLES');
            if ($stmt !== false) {
                while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
                    if (is_array($row) && isset($row[0]) && is_string($row[0])) $tables[] = $row[0];
                }
            }
        }
        return $tables;
    }

    private function rowCount(\PDO $pdo, string $table, string $driver): int {
        if ($driver !== 'mysql') return 0;
        $safe = '`' . str_replace('`', '``', $table) . '`';
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$safe}");
        if ($stmt === false) return 0;
        $stmt->execute();
        $v = $stmt->fetchColumn();
        return is_numeric($v) ? (int)$v : 0;
    }

    /** @return array{tables:array<string,int>,ts:int}|null */
    private function loadSnapshot(string $path): ?array {
        if (!is_readable($path)) return null;
        $raw = @file_get_contents($path);
        if ($raw === false) return null;
        $d = json_decode($raw, true);
        if (!is_array($d) || !isset($d['tables']) || !is_array($d['tables'])) return null;
        return ['tables' => $d['tables'], 'ts' => (int)($d['ts'] ?? 0)];
    }

    /** @param array<string,int> $counts */
    private function saveSnapshot(string $path, array $counts): void {
        $dir = dirname($path);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        @file_put_contents($path, json_encode([
            'ts' => time(),
            'tables' => $counts,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
