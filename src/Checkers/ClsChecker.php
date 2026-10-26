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
            $rel = substr($file, strlen($this->config->getTargetDir()) + 1);
            
            preg_match_all('/<img\b[^>]*>/i', $c, $m, PREG_OFFSET_CAPTURE);
            foreach ($m[0] as $match) {
                $imgTag = $match[0];
                $offset = $match[1];
                $lineNum = substr_count(substr($c, 0, $offset), "\n") + 1;
                
                if (!preg_match('/\bwidth\s*=/i', $imgTag) || !preg_match('/\bheight\s*=/i', $imgTag)) {
                    $snippet = trim(substr($imgTag, 0, 80));
                    $issues[] = sprintf('%s:%d | %s', $rel, $lineNum, $snippet);
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