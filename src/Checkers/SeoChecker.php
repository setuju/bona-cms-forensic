<?php
declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

use SatuRumah\Forensic\Core\FileSystem;

class SeoChecker extends AbstractChecker {
    public function getName(): string { return 'SEO Static Analysis'; }
    public function run(): array {
        $templates = FileSystem::scanDir($this->config->getTargetDir(), ['php', 'html', 'phtml'], ['vendor', 'node_modules', '.git']);
        $issues = []; $checked = 0;
        foreach ($templates as $file) {
            $c = @file_get_contents($file); if ($c === false) continue;
            if (stripos($c, '<head') !== false) {
                $checked++;
                $rel = substr($file, strlen($this->config->getTargetDir()) + 1);
                if (!preg_match('/<title\b/i', $c)) $issues[] = "{$rel}: Missing <title>";
                if (!preg_match('/<meta\s+name=["\']description["\']/i', $c)) $issues[] = "{$rel}: Missing <meta description>";
            }
        }
        $this->addResult('INFO', "Analyzed {$checked} templates.");
        if (!empty($issues)) {
            $this->addResult('WARN', count($issues) . " SEO issues found.", ['list' => $issues]);
            FileSystem::writeLog($this->config->getLogDir(), 'seo-issues', $issues);
        } else {
            $this->addResult('PASS', 'All templates have basic SEO tags.');
        }
        return $this->results;
    }
}