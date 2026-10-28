<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Checkers/DuplicationChecker.php
 * │  Purpose   : Copy-paste duplication via phpcpd (graceful SKIP)
 * │  Author    : Jack — Jack[at]SatuRumah[dot]Net                        │
 * │  License   : Proprietary — all rights reserved                       │
 * └──────────────────────────────────────────────────────────────────────┘
 */

declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

class DuplicationChecker extends AbstractChecker {
    public function getName(): string { return 'Code Duplication (phpcpd)'; }

    private const WARN_PERCENT = 5.0;
    private const FAIL_PERCENT = 10.0;

    public function run(): array {
        $phpcpd = $this->findTool('phpcpd');
        if ($phpcpd === null) {
            $this->addResult('SKIP',
                'phpcpd not installed. Install: composer require --dev sebastian/phpcpd');
            return $this->results;
        }

        $cmd = 'timeout 90 ' . escapeshellarg($phpcpd)
             . ' --min-lines=6 --min-tokens=70 --fuzzy'
             . ' --names-exclude=Test.php,Test,phpunit.xml,tests'
             . ' ' . escapeshellarg($this->config->getTargetDir()) . ' 2>&1';
        $out = (string)shell_exec($cmd);
        if ($out === '') {
            $this->addResult('SKIP', 'phpcpd produced no output.');
            return $this->results;
        }

        if (preg_match('/Found (\d+) clones? with (\d+) duplicated lines? in (\d+) files?/i', $out, $m)) {
            $clones = (int)$m[1];
            $lines  = (int)$m[2];
            $files  = (int)$m[3];

            $totalLines = 1;
            if (preg_match('/(\d+)\s+lines of code/i', $out, $locM)) {
                $totalLines = max(1, (int)$locM[1]);
            }
            $pct = ($lines / $totalLines) * 100;

            $summary = sprintf(
                '%d clone%s, %d duplicated lines in %d file%s (%.2f%% of %d LOC)',
                $clones, $clones === 1 ? '' : 's',
                $lines, $files, $files === 1 ? '' : 's',
                $pct, $totalLines
            );

            if ($pct > self::FAIL_PERCENT) {
                $this->addResult('FAIL', $summary . ' — above ' . self::FAIL_PERCENT . '% threshold.');
            } elseif ($pct > self::WARN_PERCENT) {
                $this->addResult('WARN', $summary . ' — above ' . self::WARN_PERCENT . '% threshold.');
            } else {
                $this->addResult('PASS', $summary);
            }
            return $this->results;
        }

        if (stripos($out, 'No clones found') !== false) {
            $this->addResult('PASS', 'No code duplication detected (phpcpd clean).');
            return $this->results;
        }

        $this->addResult('SKIP', 'Could not parse phpcpd output: ' . substr($out, 0, 120));
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
