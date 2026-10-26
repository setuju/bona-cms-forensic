<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Core/FileSystem.php
 * │  Purpose   : Read-only filesystem utility for scanning and logging
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

class FileSystem {
    public static function scanDir(string $dir, array $extensions, array $excludeDirs = []): array {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                fn($current) => !$current->isDir() || !in_array($current->getFilename(), $excludeDirs, true)
            )
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array(strtolower($file->getExtension()), $extensions, true)) {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }

    public static function writeLog(string $logDir, string $name, array $lines): string {
        $file = $logDir . "/log-{$name}-" . date('Ymd-His') . ".txt";
        @file_put_contents($file, implode("\n", $lines));
        return $file;
    }
}