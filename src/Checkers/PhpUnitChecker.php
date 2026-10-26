<?php
declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

class PhpUnitChecker extends AbstractChecker {
    public function getName(): string { return 'PHPUnit Test Suite'; }

    public function run(): array {
        $toolPhpunit = $this->config->getToolDir() . '/vendor/bin/phpunit';
        $targetPhpunit = $this->config->getTargetDir() . '/vendor/bin/phpunit';

        if (file_exists($toolPhpunit)) {
            return $this->runPhpunit($toolPhpunit, 'Tool (bona-cms-forensic)');
        }
        if (file_exists($targetPhpunit)) {
            return $this->runPhpunit($targetPhpunit, 'Target (project)');
        }
        $global = trim(shell_exec('which phpunit 2>/dev/null') ?: '');
        if (!empty($global)) {
            return $this->runPhpunit($global, 'Global (system)');
        }
        $this->addResult('SKIP', 'PHPUnit tidak ditemukan. Install di tool: composer require --dev phpunit/phpunit');
        return $this->results;
    }

    private function runPhpunit(string $phpunit, string $source): array {
        $this->addResult('INFO', "PHPUnit ditemukan di {$source}: {$phpunit}");

        $testDirs = ['tests', 'test', 'Tests'];
        $hasTests = false;
        foreach ($testDirs as $dir) {
            if (is_dir($this->config->getTargetDir() . '/' . $dir)) { $hasTests = true; break; }
        }
        if (!$hasTests) {
            $this->addResult('SKIP', 'Target tidak memiliki folder tests/. Tidak ada test yang dijalankan.');
            return $this->results;
        }

        $junit = sys_get_temp_dir() . '/bona-phpunit-' . uniqid() . '.xml';
        $cmd = 'cd ' . escapeshellarg($this->config->getTargetDir()) . ' && ' . escapeshellarg($phpunit) . ' --log-junit ' . escapeshellarg($junit) . ' --no-coverage 2>&1';
        $output = shell_exec($cmd);

        if (!file_exists($junit)) {
            $this->addResult('WARN', 'PHPUnit dijalankan tapi tidak menghasilkan JUnit XML. Config phpunit.xml mungkin tidak ada di target.');
            return $this->results;
        }

        $xml = @simplexml_load_file($junit);
        @unlink($junit);
        if (!$xml) {
            $this->addResult('WARN', 'Gagal parse JUnit XML.');
            return $this->results;
        }

        $tests = (int)($xml['tests'] ?? 0);
        $failures = (int)($xml['failures'] ?? 0);
        $errors = (int)($xml['errors'] ?? 0);

        $this->addResult('INFO', "PHPUnit Result: {$tests} tests, {$failures} failures, {$errors} errors");
        if ($failures > 0 || $errors > 0) {
            $this->addResult('FAIL', "PHPUnit gagal: {$failures} failures, {$errors} errors.", ['list' => array_slice(explode("\n", trim($output ?? '')), -5)]);
        } elseif ($tests === 0) {
            $this->addResult('WARN', 'PHPUnit tidak menemukan test.');
        } else {
            $this->addResult('PASS', "Semua {$tests} test PHPUnit lulus.");
        }
        return $this->results;
    }
}