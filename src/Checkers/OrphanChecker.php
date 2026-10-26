<?php
declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

use SatuRumah\Forensic\Core\FileSystem;

class OrphanChecker extends AbstractChecker {
    public function getName(): string { return 'Orphan Detection'; }

    public function run(): array {
        $excludeDirs = ['vendor', 'node_modules', '.git', 'tests', 'storage', 'cache', 'tmp', '.bona', 'scripts', 'migrations'];
        $files = FileSystem::scanDir($this->config->getTargetDir(), ['php'], $excludeDirs);
        $entryPoints = ['index.php', 'public/index.php', 'app.php', 'router.php', 'install.php'];
        $entryAbs = array_map(fn($e) => realpath($this->config->getTargetDir() . '/' . $e) ?: $this->config->getTargetDir() . '/' . $e, $entryPoints);

        $reverseGraph = [];
        $requireTokens = [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE];
        
        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content === false) continue;
            $tokens = @token_get_all($content);
            if (!$tokens) continue;
            
            $i = 0; $n = count($tokens);
            while ($i < $n) {
                $t = $tokens[$i];
                if (!is_array($t) || !in_array($t[0], $requireTokens, true)) { $i++; continue; }
                [$path] = (new DependencyChecker($this->config))->extractPath($tokens, $i, $n);
                if ($path !== null) {
                    $resolved = (new DependencyChecker($this->config))->resolvePath($path, $file);
                    if ($resolved !== null && file_exists($resolved)) $reverseGraph[$resolved][] = $file;
                }
                while ($i < $n && $tokens[$i] !== ';') $i++; $i++;
            }
        }

        $orphans = [];
        foreach ($files as $f) {
            if (in_array($f, $entryAbs, true)) continue;
            if (empty($reverseGraph[$f])) {
                $c = @file_get_contents($f);
                if ($c && preg_match('/\b(class|interface|trait)\s+\w+/i', $c)) continue;
                $orphans[] = substr($f, strlen($this->config->getTargetDir()) + 1);
            }
        }

        if (empty($orphans)) {
            $this->addResult('PASS', 'No orphan files detected.');
        } else {
            $this->addResult('WARN', "Found " . count($orphans) . " potential orphan files.", ['list' => $orphans]);
            $logFile = FileSystem::writeLog($this->config->getLogDir(), 'orphans', $orphans);
            $this->addResult('INFO', "Orphan list written to log.", ['file' => $logFile]);
        }
        return $this->results;
    }
}