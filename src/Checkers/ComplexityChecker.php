<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Checkers/ComplexityChecker.php
 * │  Purpose   : Cyclomatic complexity via phploc (graceful SKIP)
 * │  Author    : Jack — Jack[at]SatuRumah[dot]Net                        │
 * │  License   : Proprietary — all rights reserved                       │
 * └──────────────────────────────────────────────────────────────────────┘
 */

declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

class ComplexityChecker extends AbstractChecker {
    public function getName(): string { return 'Code Complexity (phploc)'; }

    private const WARN_COMPLEXITY = 20.0;
    private const FAIL_COMPLEXITY = 40.0;

    public function run(): array {
        $phploc = $this->findTool('phploc');
        if ($phploc === null) {
            $this->addResult('SKIP',
                'phploc not installed. Install: composer require --dev phploc/phploc');
            return $this->results;
        }

        $targetDir = $this->config->getTargetDir();
        $cmd = 'timeout 60 ' . escapeshellarg($phploc)
             . ' --exclude=vendor --exclude=node_modules --exclude=.git'
             . ' --exclude=tests --exclude=.bona --exclude=cache'
             . ' --exclude=build --exclude=dist --exclude=coverage'
             . ' ' . escapeshellarg($targetDir) . ' 2>&1';
        $out = shell_exec($cmd);
        if (!is_string($out) || $out === '') {
            $this->addResult('SKIP', 'phploc execution failed or timed out.');
            return $this->results;
        }

        if (!preg_match('/Average Complexity per Method\s+([\d.]+)/i', $out, $m)) {
            $this->addResult('SKIP', 'Could not parse phploc output (unexpected format).');
            return $this->results;
        }

        $avg = (float)$m[1];
        $summary = sprintf('Average cyclomatic complexity per method: %.2f (phploc)', $avg);

        if ($avg > self::FAIL_COMPLEXITY) {
            $this->addResult('FAIL', $summary . ' — above ' . self::FAIL_COMPLEXITY . ' threshold.');
        } elseif ($avg > self::WARN_COMPLEXITY) {
            $this->addResult('WARN', $summary . ' — above ' . self::WARN_COMPLEXITY . ' threshold.');
        } else {
            $this->addResult('PASS', $summary);
        }
        return $this->results;
    }

    private function findTool(string $name): ?string {
        $vendorBin = $this->config->getTargetDir() . '/vendor/bin/' . $name;
        if (is_file($vendorBin) && is_executable($vendorBin)) return $vendorBin;

        $toolVendorBin = $this->config->getToolDir() . '/vendor/bin/' . $name;
        if (is_file($toolVendorBin) && is_executable($toolVendorBin)) return $toolVendorBin;

        $path = trim((string)shell_exec('which ' . escapeshellarg($name) . ' 2>/dev/null'));
        return $path !== '' ? $path : null;
    }
}
