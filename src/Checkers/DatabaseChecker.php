<?php
declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

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
}