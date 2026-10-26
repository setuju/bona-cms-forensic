<?php
declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

use SatuRumah\Forensic\Core\FileSystem;

class SeoChecker extends AbstractChecker {
    public function getName(): string { return 'SEO Static Analysis'; }
    public function run(): array {
        // Hanya scan folder yang memang berisi halaman web (templates, views, admin, root)
        // Exclude: includes (logika), system (CLI), vendor
        $templates = FileSystem::scanDir(
            $this->config->getTargetDir(), 
            ['php', 'html', 'phtml'], 
            ['vendor', 'node_modules', '.git', 'includes', 'system', 'api', 'cron', 'migrations', 'scripts', 'storage', 'cache']
        );
        
        $issues = []; $checked = 0;
        
        foreach ($templates as $file) {
            $c = @file_get_contents($file); if ($c === false) continue;
            
            // Hanya cek jika file benar-benar halaman HTML utuh
            if (preg_match('/<!DOCTYPE\s+html/i', $c) || (preg_match('/<html\b/i', $c) && preg_match('/<head\b/i', $c))) {
                // Skip jika ini template email (biasanya ada di fungsi mailer)
                if (stripos($file, 'mailer') !== false || stripos($file, 'email') !== false) continue;
                // Skip jika file hanya berisi string (bukan HTML output langsung)
                if (preg_match('/\$\w+mailer/i', $c)) continue;
                
                $checked++;
                $rel = substr($file, strlen($this->config->getTargetDir()) + 1);
                if (!preg_match('/<title\b[^>]*>.+?<\/title>/is', $c)) $issues[] = "{$rel}: Missing or empty <title>";
                if (!preg_match('/<meta\s+name=["\']description["\'][^>]*content=["\'][^"\']+["\']/i', $c)) $issues[] = "{$rel}: Missing or empty <meta description>";
            }
        }
        
        $this->addResult('INFO', "Analyzed {$checked} real HTML pages.");
        if (!empty($issues)) {
            $this->addResult('WARN', count($issues) . " SEO issues found.", ['list' => $issues]);
            FileSystem::writeLog($this->config->getLogDir(), 'seo-issues', $issues);
        } else {
            $this->addResult('PASS', 'All HTML pages have SEO tags.');
        }
        return $this->results;
    }
}