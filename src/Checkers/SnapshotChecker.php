<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Checkers/SnapshotChecker.php
 * │  Purpose   : Snapshot and diff tracker across audit runs
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

use SatuRumah\Forensic\Core\SnapshotManager;
use SatuRumah\Forensic\Core\FileSystem;

class SnapshotChecker extends AbstractChecker {
    private array $currentResults;
    private SnapshotManager $manager;

    public function __construct($config, array $currentResults) {
        parent::__construct($config);
        $this->currentResults = $currentResults;
        $this->manager = new SnapshotManager($config->getToolDir());
    }

    public function getName(): string { return 'Snapshot & Diff'; }

    public function run(): array {
        $files = FileSystem::scanDir($this->config->getTargetDir(), ['php'], ['vendor', 'node_modules', '.git']);
        $fileHashes = [];
        foreach ($files as $file) {
            $rel = substr($file, strlen($this->config->getTargetDir()) + 1);
            $fileHashes[$rel] = md5_file($file);
        }

        // Data yang akan disimpan
        $currentData = [
            'timestamp' => date('c'),
            'files' => $fileHashes,
            'fails' => $this->countStatus('FAIL'),
            'warns' => $this->countStatus('WARN')
        ];

        $oldData = $this->manager->load();

        if ($oldData === null) {
            $this->addResult('INFO', 'First run. Snapshot baseline created.');
            $this->manager->save($currentData);
            return $this->results;
        }

        // Lakukan Diff
        $oldFiles = $oldData['files'] ?? [];
        $newFiles = array_diff_key($fileHashes, $oldFiles);
        $removedFiles = array_diff_key($oldFiles, $fileHashes);
        $modifiedFiles = [];
        
        foreach ($fileHashes as $f => $hash) {
            if (isset($oldFiles[$f]) && $oldFiles[$f] !== $hash) $modifiedFiles[] = $f;
        }

        $diffCount = count($newFiles) + count($removedFiles) + count($modifiedFiles);
        
        if ($diffCount === 0 && $oldData['fails'] === $currentData['fails']) {
            $this->addResult('PASS', 'No changes detected since last snapshot.');
        } else {
            if (count($newFiles) > 0) $this->addResult('INFO', count($newFiles) . ' new files added.');
            if (count($removedFiles) > 0) $this->addResult('INFO', count($removedFiles) . ' files removed.');
            if (count($modifiedFiles) > 0) $this->addResult('INFO', count($modifiedFiles) . ' files modified.');
            
            if ($currentData['fails'] > $oldData['fails']) {
                $this->addResult('WARN', "Quality degraded! FAILs increased from {$oldData['fails']} to {$currentData['fails']}.");
            } elseif ($currentData['fails'] < $oldData['fails']) {
                $this->addResult('PASS', "Quality improved! FAILs decreased from {$oldData['fails']} to {$currentData['fails']}.");
            }
        }

        // Simpan snapshot baru
        $this->manager->save($currentData);
        return $this->results;
    }

    private function countStatus(string $status): int {
        $count = 0;
        foreach ($this->currentResults as $checker => $results) {
            foreach ($results as $res) {
                if ($res['status'] === $status) $count++;
            }
        }
        return $count;
    }
}