<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : src/Checkers/ConfigChecker.php
 * │  Purpose   : Environment and configuration auditor (php.ini, CVE, CMS features)
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

use SatuRumah\Forensic\Core\FileSystem;

class ConfigChecker extends AbstractChecker {
    public function getName(): string { return 'Environment & Configuration'; }

    public function run(): array {
        // 1. PHP Config
        $bad = 0;
        if (ini_get('display_errors') === '1') { $this->addResult('WARN', 'display_errors is ON'); $bad++; }
        if (ini_get('allow_url_include') === '1') { $this->addResult('WARN', 'allow_url_include is ON'); $bad++; }
        if ($bad === 0) { $this->addResult('PASS', 'PHP configuration is secure.'); }

        // 2. CVE Audit
        $composerLock = $this->config->getTargetDir() . '/composer.lock';
        if (file_exists($composerLock)) {
            $out = []; $ret = 0;
            @exec('cd ' . escapeshellarg($this->config->getTargetDir()) . ' && composer audit --format=json 2>&1', $out, $ret);
            $json = @json_decode(implode("\n", $out), true);
            if (is_array($json) && isset($json['advisories']) && count($json['advisories']) > 0) {
                $this->addResult('FAIL', count($json['advisories']) . ' vulnerable packages found.', ['list' => array_keys($json['advisories'])]);
            } else { $this->addResult('PASS', 'No known CVEs in dependencies.'); }
        } else { $this->addResult('SKIP', 'No composer.lock found for CVE audit.'); }

        // 3. CMS Security Features (Basic Regex Check)
        $features = ['password_hash' => '/password_hash\s*\(/i', 'CSRF Token' => '/(csrf.?token|xsrf)/i', 'MFA/2FA' => '/(mfa|2fa|totp|two.?factor)/i'];
        $found = 0;
        foreach (FileSystem::scanDir($this->config->getTargetDir(), ['php'], ['vendor']) as $file) {
            $c = @file_get_contents($file); if (!$c) continue;
            foreach ($features as $name => $regex) {
                if (preg_match($regex, $c)) { $this->addResult('PASS', "Detected CMS feature: {$name}"); $found++; unset($features[$name]); }
            }
        }
        foreach (array_keys($features) as $missing) {
            $this->addResult('WARN', "Missing CMS feature: {$missing}");
        }

        return $this->results;
    }
}