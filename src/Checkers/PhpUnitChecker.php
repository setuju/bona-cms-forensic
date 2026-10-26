<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Checkers/PhpUnitChecker.php
 * │  Purpose   : PHPUnit test suite runner and JUnit result parser
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

class PhpUnitChecker extends AbstractChecker {
    public function getName(): string { return 'PHPUnit Test Suite'; }

    public function run(): array {
        // Cek apakah target memiliki konfigurasi PHPUnit
        $configCandidates = ['phpunit.xml', 'phpunit.xml.dist', 'phpunit.dist.xml'];
        $hasConfig = false;
        foreach ($configCandidates as $cfg) {
            if (file_exists($this->config->getTargetDir() . '/' . $cfg)) { $hasConfig = true; break; }
        }
        if (!$hasConfig) {
            $this->addResult('SKIP', 'Target tidak memiliki phpunit.xml. Skipping (tidak ada test configuration).');
            return $this->results;
        }

        $toolPhpunit = $this->config->getToolDir() . '/vendor/bin/phpunit';
        $targetPhpunit = $this->config->getTargetDir() . '/vendor/bin/phpunit';
        $phpunit = null; $source = '';

        if (file_exists($targetPhpunit)) { $phpunit = $targetPhpunit; $source = 'Target'; }
        elseif (file_exists($toolPhpunit)) { $phpunit = $toolPhpunit; $source = 'Tool'; }
        else {
            $global = trim(shell_exec('which phpunit 2>/dev/null') ?: '');
            if (!empty($global)) { $phpunit = $global; $source = 'Global'; }
        }

        if ($phpunit === null) {
            $this->addResult('SKIP', 'PHPUnit binary tidak ditemukan.');
            return $this->results;
        }
        $this->addResult('INFO', "PHPUnit ({$source}): {$phpunit}");

        // Jalankan dalam mode read-only (hanya baca, --log-junit ke /tmp)
        $junit = sys_get_temp_dir() . '/bona-phpunit-' . uniqid() . '.xml';
        $cmd = 'cd ' . escapeshellarg($this->config->getTargetDir()) 
             . ' && ' . escapeshellarg($phpunit) 
             . ' --log-junit ' . escapeshellarg($junit) 
             . ' --no-coverage 2>&1';
        $output = shell_exec($cmd);

        if (!file_exists($junit)) {
            $this->addResult('WARN', 'PHPUnit tidak menghasilkan JUnit XML. Output: ' . substr($output ?? '', 0, 200));
            return $this->results;
        }

        $xml = @simplexml_load_file($junit);
        @unlink($junit);
        if (!$xml) {
            $this->addResult('WARN', 'Gagal parse JUnit XML (kemungkinan error konfigurasi).');
            return $this->results;
        }

        $tests = (int)($xml['tests'] ?? 0);
        $failures = (int)($xml['failures'] ?? 0);
        $errors = (int)($xml['errors'] ?? 0);

        $this->addResult('INFO', "PHPUnit: {$tests} tests, {$failures} failures, {$errors} errors");
        if ($failures > 0 || $errors > 0) {
            $this->addResult('FAIL', "PHPUnit gagal: {$failures} failures, {$errors} errors.");
        } elseif ($tests === 0) {
            $this->addResult('WARN', 'PHPUnit tidak menemukan test (empty suite).');
        } else {
            $this->addResult('PASS', "Semua {$tests} test lulus.");
        }
        return $this->results;
    }
}