<?php
declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

class Php2ShellChecker extends AbstractChecker {
    public function getName(): string { return 'PHP2Shell (CVE-2026-109618/109619)'; }

    public function run(): array {
        $currentVersion = PHP_VERSION;
        $this->addResult('INFO', "PHP Runtime Version: {$currentVersion}");

        // 1. Cek versi PHP terhadap CVE php2shell
        $vulnerable = false;
        $fixedVersion = '';
        if (version_compare($currentVersion, '8.5.0', '>=') && version_compare($currentVersion, '8.5.12', '<')) {
            $vulnerable = true; $fixedVersion = '8.5.12';
        } elseif (version_compare($currentVersion, '8.4.0', '>=') && version_compare($currentVersion, '8.4.27', '<')) {
            $vulnerable = true; $fixedVersion = '8.4.27';
        } elseif (version_compare($currentVersion, '8.3.0', '>=') && version_compare($currentVersion, '8.3.36', '<')) {
            $vulnerable = true; $fixedVersion = '8.3.36';
        } elseif (version_compare($currentVersion, '8.2.0', '>=') && version_compare($currentVersion, '8.2.35', '<')) {
            $vulnerable = true; $fixedVersion = '8.2.35';
        } elseif (version_compare($currentVersion, '8.2.0', '<')) {
            $vulnerable = true; $fixedVersion = '8.2.35 (atau lebih baru)';
        }

        if ($vulnerable) {
            $this->addResult('FAIL', "PHP {$currentVersion} RENTAN terhadap php2shell RCE. Segera update ke >= {$fixedVersion}.");
        } else {
            $this->addResult('PASS', "PHP {$currentVersion} tidak rentan terhadap php2shell.");
        }

        // 2. Cek mitigasi memory_limit
        $memLimit = ini_get('memory_limit');
        if ($memLimit === '-1') {
            $this->addResult('PASS', 'memory_limit = -1 (mitigasi CVE-2026-109618 aktif).');
        } else {
            $this->addResult('WARN', "memory_limit = {$memLimit} (disarankan -1 untuk mitigasi php2shell, dengan cgroup limit).");
        }

        // 3. Cek post_max_size
        $postMax = ini_get('post_max_size');
        $postMaxBytes = $this->toBytes($postMax);
        if ($postMaxBytes > 32 * 1024 * 1024) {
            $this->addResult('WARN', "post_max_size = {$postMax} (terlalu besar, disarankan 8M-32M).");
        } else {
            $this->addResult('PASS', "post_max_size = {$postMax} (dalam batas aman).");
        }

        // 4. Cek session.upload_progress
        if (ini_get('session.upload_progress.enabled') === '1') {
            $this->addResult('WARN', 'session.upload_progress.enabled = On (disarankan Off untuk mitigasi CVE-2026-109619).');
        } else {
            $this->addResult('PASS', 'session.upload_progress.enabled = Off.');
        }

        return $this->results;
    }

    private function toBytes(string $val): int {
        $val = trim($val); $last = strtolower($val[strlen($val)-1] ?? '');
        $num = (int)$val;
        return match($last) { 'g' => $num * 1024 * 1024 * 1024, 'm' => $num * 1024 * 1024, 'k' => $num * 1024, default => $num };
    }
}