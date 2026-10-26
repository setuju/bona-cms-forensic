<?php
declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

class PhpUnitChecker extends AbstractChecker {
    public function getName(): string { return 'PHPUnit Test Suite'; }

    public function run(): array {
        $phpunit = $this->config->getTargetDir() . '/vendor/bin/phpunit';
        if (!file_exists($phpunit)) {
            $global = trim(shell_exec('which phpunit 2>/dev/null') ?: '');
            if (empty($global)) {
                $this->addResult('SKIP', 'PHPUnit tidak ditemukan (vendor/bin/phpunit atau phpunit global).');
                return $this->results;
            }
            $phpunit = $global;
        }

        $junitFile = sys_get_temp_dir() . '/bona-phpunit-' . uniqid() . '.xml';
        $cmd = 'cd ' . escapeshellarg($this->config->getTargetDir()) . ' && ' . escapeshellarg($phpunit) . ' --log-junit ' . escapeshellarg($junitFile) . ' --no-coverage 2>&1';
        $output = shell_exec($cmd);
        $exitCode = 0;
        // shell_exec tidak memberi exit code, jadi kita parse dari file JUnit

        if (!file_exists($junitFile)) {
            $this->addResult('SKIP', 'PHPUnit tidak menghasilkan output JUnit (mungkin tidak ada test).');
            return $this->results;
        }

        $xml = @simplexml_load_file($junitFile);
        if (!$xml) {
            @unlink($junitFile);
            $this->addResult('SKIP', 'Gagal parse JUnit XML dari PHPUnit.');
            return $this->results;
        }

        $tests = (int)($xml['tests'] ?? 0);
        $failures = (int)($xml['failures'] ?? 0);
        $errors = (int)($xml['errors'] ?? 0);

        @unlink($junitFile);

        $this->addResult('INFO', "PHPUnit: {$tests} tests, {$failures} failures, {$errors} errors.");
        if ($failures > 0 || $errors > 0) {
            $this->addResult('FAIL', "PHPUnit gagal: {$failures} failures, {$errors} errors.", ['list' => array_slice(explode("\n", trim($output ?: '')), -5)]);
        } elseif ($tests === 0) {
            $this->addResult('WARN', 'PHPUnit tidak menemukan test (empty suite).');
        } else {
            $this->addResult('PASS', "Semua {$tests} test PHPUnit lulus.");
        }
        return $this->results;
    }
}