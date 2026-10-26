<?php
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

        $this->addResult('INFO', "Found config: {$creds['driver']}://{$creds['host']}/{$creds['database']}");

        try {
            $pdo = $this->connect($creds);
            $this->addResult('PASS', 'Database connection successful (READ-ONLY mode).');
        } catch (\Exception $e) {
            $this->addResult('SKIP', 'Connection failed (local vs prod diff): ' . substr($e->getMessage(), 0, 100));
            return $this->results;
        }

        $this->analyzeSchema($pdo, $creds['driver']);
        return $this->results;
    }

    private function findCredentials(): ?array {
        // Cari di beberapa lokasi config umum
        $configFiles = [
            'config.php', 'config.local.php', '.env',
            'config/database.php', 'includes/db.php', 'includes/config.php',
            'app/config/database.php', 'system/config.php'
        ];

        foreach ($configFiles as $file) {
            $path = $this->config->getTargetDir() . '/' . $file;
            if (!file_exists($path)) continue;
            $content = @file_get_contents($path);
            if ($content === false) continue;

            // Pola 1: PHP Array style: 'db_host' => 'localhost', 'db_name' => 'xxx', ...
            if (preg_match('/(?:db_host|DB_HOST|host)["\']?\s*(?:=>|=)\s*["\']([^"\']+)["\']/', $content, $host) &&
                preg_match('/(?:db_name|DB_NAME|dbname|database)["\']?\s*(?:=>|=)\s*["\']([^"\']+)["\']/', $content, $db) &&
                preg_match('/(?:db_user|DB_USER|username|user)["\']?\s*(?:=>|=)\s*["\']([^"\']+)["\']/', $content, $user)) {
                
                // Password bisa kosong
                $pass = '';
                if (preg_match('/(?:db_pass|DB_PASS|db_password|password|pass)["\']?\s*(?:=>|=)\s*["\']([^"\']*)["\']/', $content, $p)) {
                    $pass = $p[1];
                }
                return ['driver' => 'mysql', 'host' => $host[1], 'database' => $db[1], 'user' => $user[1], 'pass' => $pass];
            }

            // Pola 2: .env style: DB_HOST=localhost \n DB_DATABASE=xxx ...
            if (preg_match('/DB_HOST=([^\s\r\n]+)/', $content, $host) &&
                preg_match('/DB_(?:DATABASE|NAME)=([^\s\r\n]+)/', $content, $db) &&
                preg_match('/DB_USER(?:NAME)?=([^\s\r\n]+)/', $content, $user)) {
                $pass = '';
                if (preg_match('/DB_PASSWORD=([^\s\r\n]*)/', $content, $p)) $pass = $p[1];
                return ['driver' => 'mysql', 'host' => $host[1], 'database' => $db[1], 'user' => $user[1], 'pass' => $pass];
            }

            // Pola 3: SQLite path
            if (preg_match('/["\'](?:db_path|sqlite_path|database_path)["\']\s*=>\s*["\']([^"\']+\.(?:sqlite|db))["\']/i', $content, $sqlite)) {
                $sqlitePath = $sqlite[1];
                if (!str_starts_with($sqlitePath, '/')) $sqlitePath = $this->config->getTargetDir() . '/' . $sqlitePath;
                if (file_exists($sqlitePath)) {
                    return ['driver' => 'sqlite', 'path' => $sqlitePath];
                }
            }
        }
        return null;
    }

    private function connect(array $creds): \PDO {
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_TIMEOUT => 3,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            // READ-ONLY mode jika didukung
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION TRANSACTION READ ONLY",
        ];

        if ($creds['driver'] === 'sqlite') {
            return new \PDO("sqlite:{$creds['path']}", null, null, $options);
        }
        $dsn = "mysql:host={$creds['host']};dbname={$creds['database']};charset=utf8mb4";
        return new \PDO($dsn, $creds['user'], $creds['pass'], $options);
    }

    private function analyzeSchema(\PDO $pdo, string $driver): void {
        try {
            if ($driver === 'mysql') {
                $this->analyzeMySQL($pdo);
            } elseif ($driver === 'sqlite') {
                $this->analyzeSQLite($pdo);
            }
        } catch (\Exception $e) {
            $this->addResult('WARN', 'Schema analysis failed: ' . substr($e->getMessage(), 0, 100));
        }
    }

    private function analyzeMySQL(\PDO $pdo): void {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
        $this->addResult('INFO', 'Tables found: ' . count($tables));

        $noPK = []; $noIndexFK = []; $dangerousCols = [];

        foreach ($tables as $table) {
            // 1. Cek PRIMARY KEY
            $pk = $pdo->query("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'")->fetchAll();
            if (empty($pk)) $noPK[] = $table;

            // 2. Cek kolom TEXT/BLOB (storage untuk XSS payload)
            $cols = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll();
            foreach ($cols as $col) {
                $type = strtoupper($col['Type']);
                if ((str_contains($type, 'TEXT') || str_contains($type, 'BLOB')) && 
                    preg_match('/(content|body|description|excerpt|message|bio|comment|data)/i', $col['Field'])) {
                    $dangerousCols[] = "{$table}.{$col['Field']} ({$col['Type']})";
                }
            }

            // 3. Cek FK tanpa index
            try {
                $fks = $pdo->query("
                    SELECT COLUMN_NAME, REFERENCED_TABLE_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = '{$table}' 
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ")->fetchAll();
                foreach ($fks as $fk) {
                    $hasIndex = $pdo->query("
                        SELECT COUNT(*) as c FROM information_schema.STATISTICS 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = '{$table}' 
                        AND COLUMN_NAME = '{$fk['COLUMN_NAME']}'
                    ")->fetch();
                    if ((int)$hasIndex['c'] === 0) {
                        $noIndexFK[] = "{$table}.{$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}";
                    }
                }
            } catch (\Exception $e) { /* Skip jika tidak punya akses information_schema */ }
        }

        if (!empty($noPK)) {
            $this->addResult('WARN', count($noPK) . ' tables WITHOUT PRIMARY KEY (rentan UPDATE/DELETE massal):', ['list' => $noPK]);
        } else {
            $this->addResult('PASS', 'All tables have PRIMARY KEY.');
        }

        if (!empty($noIndexFK)) {
            $this->addResult('WARN', count($noIndexFK) . ' FKs without index (slow JOIN):', ['list' => $noIndexFK]);
        }

        if (!empty($dangerousCols)) {
            $this->addResult('INFO', count($dangerousCols) . ' TEXT/BLOB columns (potential XSS storage):', ['list' => array_slice($dangerousCols, 0, 10)]);
        }
    }

    private function analyzeSQLite(\PDO $pdo): void {
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(\PDO::FETCH_COLUMN);
        $this->addResult('INFO', 'SQLite tables: ' . count($tables));

        $noPK = [];
        foreach ($tables as $table) {
            $cols = $pdo->query("PRAGMA table_info(`{$table}`)")->fetchAll();
            $hasPK = false;
            foreach ($cols as $col) if ((int)$col['pk'] === 1) { $hasPK = true; break; }
            if (!$hasPK) $noPK[] = $table;
        }

        if (!empty($noPK)) {
            $this->addResult('WARN', count($noPK) . ' tables WITHOUT PRIMARY KEY:', ['list' => $noPK]);
        } else {
            $this->addResult('PASS', 'All SQLite tables have PRIMARY KEY.');
        }
    }
}