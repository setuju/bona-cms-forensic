<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : tests/Unit/FrameworkCheckerTest.php
 * │  Purpose   : Unit tests for Checkers\FrameworkChecker detection
 * │  License   : Proprietary — all rights reserved                       │
 * └──────────────────────────────────────────────────────────────────────┘
 */

declare(strict_types=1);
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SatuRumah\Forensic\Checkers\FrameworkChecker;
use SatuRumah\Forensic\Core\Config;

final class FrameworkCheckerTest extends TestCase {
    private string $tmp;

    protected function setUp(): void {
        $this->tmp = sys_get_temp_dir() . '/bona-fw-' . bin2hex(random_bytes(4));
        mkdir($this->tmp, 0755, true);
    }

    protected function tearDown(): void {
        if (is_dir($this->tmp)) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->tmp, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $f) {
                $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
            }
            rmdir($this->tmp);
        }
    }

    public function testGetNameReturnsExpected(): void {
        $config = new Config($this->tmp, false);
        $checker = new FrameworkChecker($config);
        $this->assertSame('Framework Detection', $checker->getName());
    }

    public function testNoFrameworkDetected(): void {
        $config = new Config($this->tmp, false);
        $checker = new FrameworkChecker($config);
        $results = $checker->run();
        $this->assertNotEmpty($results);
        $this->assertSame('INFO', $results[0]['status']);
        $this->assertStringContainsString('No framework markers', $results[0]['message']);
    }

    public function testWordPressDetected(): void {
        file_put_contents($this->tmp . '/wp-config.php', '<?php');
        mkdir($this->tmp . '/wp-content', 0755, true);
        $config = new Config($this->tmp, false);
        $checker = new FrameworkChecker($config);
        $results = $checker->run();
        $found = false;
        foreach ($results as $r) {
            if (str_contains($r['message'], 'WordPress')) { $found = true; break; }
        }
        $this->assertTrue($found, 'WordPress detection failed');
    }

    public function testLaravelDetected(): void {
        mkdir($this->tmp . '/bootstrap', 0755, true);
        file_put_contents($this->tmp . '/artisan', '<?php');
        file_put_contents($this->tmp . '/bootstrap/app.php', '<?php');
        $config = new Config($this->tmp, false);
        $checker = new FrameworkChecker($config);
        $results = $checker->run();
        $found = false;
        foreach ($results as $r) {
            if (str_contains($r['message'], 'Laravel')) { $found = true; break; }
        }
        $this->assertTrue($found, 'Laravel detection failed');
    }
}
