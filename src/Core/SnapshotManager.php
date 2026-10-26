<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Core/SnapshotManager.php
 * │  Purpose   : Snapshot persistence manager (JSON storage)
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
 * @package   SatuRumah\Forensic\Core
 * @license   Proprietary
 */

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