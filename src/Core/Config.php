<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Core/Config.php
 * │  Purpose   : Configuration manager for target and tool directories
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

class Config {
    private string $targetDir;
    private string $toolDir;
    private bool $showFindings;

    public function __construct(string $targetDir, bool $showFindings = false) {
        if (str_starts_with($targetDir, '~/')) {
            $targetDir = getenv('HOME') . substr($targetDir, 1);
        }
        
        $real = realpath($targetDir);
        if ($real === false) {
            echo "\033[0;31m[FATAL] Directory not found: {$targetDir}\033[0m\n";
            exit(1);
        }
        
        $this->targetDir = rtrim($real, '/');
        // Paksa toolDir menggunakan path absolut dari file ini
        $this->toolDir = dirname(__DIR__, 2);
        $this->showFindings = $showFindings;
    }

    public function getTargetDir(): string { return $this->targetDir; }
    public function getToolDir(): string { return $this->toolDir; }
    public function getLogDir(): string { return $this->toolDir . '/.bona/logs'; }
    public function shouldShowFindings(): bool { return $this->showFindings; }
}