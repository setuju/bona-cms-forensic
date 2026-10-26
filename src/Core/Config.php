<?php
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
        
        // FIX BUG: Gunakan realpath agar '.' menjadi path absolut (mencegah bug str_replace)
        $real = realpath($targetDir);
        if ($real === false) {
            echo "\033[0;31m[FATAL] Directory not found: {$targetDir}\033[0m\n";
            exit(1);
        }
        
        $this->targetDir = rtrim($real, '/');
        $this->toolDir = dirname(__DIR__, 2);
        $this->showFindings = $showFindings;
    }

    public function getTargetDir(): string { return $this->targetDir; }
    public function getToolDir(): string { return $this->toolDir; }
    public function getLogDir(): string { return $this->toolDir . '/.bona/logs'; }
    public function shouldShowFindings(): bool { return $this->showFindings; }
}