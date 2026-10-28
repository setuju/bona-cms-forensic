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

    /** @var array<int, string> */
    private static array $globalIgnorePrefixes = [];

    /** @param array<int, string> $prefixes */
    public static function setIgnorePrefixes(array $prefixes): void {
        self::$globalIgnorePrefixes = $prefixes;
    }

    public static function isIgnored(string $relPath): bool {
        foreach (self::$globalIgnorePrefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($relPath, $prefix)) return true;
        }
        return false;
    }
    public static function scanDir(string $dir, array $extensions, array $excludeDirs = []): array {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                fn($current) => !$current->isDir() || !in_array($current->getFilename(), $excludeDirs, true)
            )
        );
        $dirLen = strlen(rtrim($dir, '/')) + 1;
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array(strtolower($file->getExtension()), $extensions, true)) {
                $full = $file->getPathname();
                $rel = substr($full, $dirLen);
                if (self::isIgnored($rel)) continue;
                $files[] = $full;
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