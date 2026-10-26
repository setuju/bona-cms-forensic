<?php
declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

use SatuRumah\Forensic\Core\FileSystem;

class IntegrityChecker extends AbstractChecker {
    private array $beforeHashes = [];

    public function getName(): string { return 'Read-Only Integrity Check'; }

    public function snapshot(): void {
        // Dipanggil sebelum semua audit dimulai
        $this->beforeHashes = $this->hashTarget();
    }

    public function run(): array {
        // Dipanggil setelah semua audit selesai
        $afterHashes = $this->hashTarget();
        
        $added = array_diff_key($afterHashes, $this->beforeHashes);
        $removed = array_diff_key($this->beforeHashes, $afterHashes);
        $modified = [];
        foreach ($afterHashes as $file => $hash) {
            if (isset($this->beforeHashes[$file]) && $this->beforeHashes[$file] !== $hash) {
                $modified[] = $file;
            }
        }

        $totalChanges = count($added) + count($removed) + count($modified);

        if ($totalChanges === 0) {
            $this->addResult('PASS', 'Target directory TIDAK BERUBAH. ' . count($afterHashes) . ' files verified read-only.');
        } else {
            $details = [];
            if (!empty($added)) $details[] = 'ADDED: ' . implode(', ', array_slice(array_keys($added), 0, 5));
            if (!empty($removed)) $details[] = 'REMOVED: ' . implode(', ', array_slice(array_keys($removed), 0, 5));
            if (!empty($modified)) $details[] = 'MODIFIED: ' . implode(', ', array_slice($modified, 0, 5));
            $this->addResult('FAIL', "CRITICAL: Target directory BERUBAH! {$totalChanges} changes detected.", ['list' => $details]);
        }
        return $this->results;
    }

    private function hashTarget(): array {
        $files = FileSystem::scanDir(
            $this->config->getTargetDir(), 
            ['php', 'phtml', 'inc', 'js', 'css', 'html', 'json', 'xml', 'yaml', 'yml', 'md', 'txt', 'sql', 'env', 'htaccess'],
            ['vendor', 'node_modules', '.git', '.bona']
        );
        $hashes = [];
        foreach ($files as $f) {
            $rel = substr($f, strlen($this->config->getTargetDir()) + 1);
            $hashes[$rel] = md5_file($f);
        }
        return $hashes;
    }
}