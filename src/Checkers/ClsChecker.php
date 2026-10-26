<?php
declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

use SatuRumah\Forensic\Core\FileSystem;

class ClsChecker extends AbstractChecker {
    public function getName(): string { return 'CLS Prevention'; }
    public function run(): array {
        $templates = FileSystem::scanDir($this->config->getTargetDir(), ['php', 'html', 'phtml'], ['vendor', 'node_modules', '.git']);
        $issues = [];
        foreach ($templates as $file) {
            $c = @file_get_contents($file); if ($c === false) continue;
            preg_match_all('/<img\b[^>]*>/i', $c, $m);
            foreach ($m[0] as $img) {
                if (!preg_match('/\bwidth\s*=/i', $img) || !preg_match('/\bheight\s*=/i', $img)) {
                    $rel = substr($file, strlen($this->config->getTargetDir()) + 1);
                    $issues[] = "{$rel}: <img> without width/height";
                }
            }
        }
        if (!empty($issues)) {
            $this->addResult('WARN', count($issues) . " CLS risks found.", ['list' => $issues]);
            FileSystem::writeLog($this->config->getLogDir(), 'cls-issues', $issues);
        } else {
            $this->addResult('PASS', 'No CLS risks detected.');
        }
        return $this->results;
    }
}