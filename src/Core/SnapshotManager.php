<?php
declare(strict_types=1);
namespace SatuRumah\Forensic\Core;

class SnapshotManager {
    private string $snapshotFile;

    public function __construct(string $toolDir) {
        $this->snapshotFile = $toolDir . '/.bona/snapshots/latest.json';
        $dir = dirname($this->snapshotFile);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
    }

    public function load(): ?array {
        if (!file_exists($this->snapshotFile)) return null;
        $data = @json_decode(@file_get_contents($this->snapshotFile), true);
        return is_array($data) ? $data : null;
    }

    public function save(array $data): void {
        @file_put_contents($this->snapshotFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}