<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : tests/Unit/FileSystemTest.php
 * │  Purpose   : Unit tests for Core\FileSystem::scanDir
 * │  Author    : Jack — Jack[at]SatuRumah[dot]Net                        │
 * │  License   : Proprietary — all rights reserved                       │
 * └──────────────────────────────────────────────────────────────────────┘
 *
 * @package   SatuRumah\Forensic\Tests
 * @license   Proprietary
 */

declare(strict_types=1);
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SatuRumah\Forensic\Core\FileSystem;

final class FileSystemTest extends TestCase {
    private string $tmp;

    protected function setUp(): void {
        $this->tmp = sys_get_temp_dir() . '/bona-fs-' . bin2hex(random_bytes(4));
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

    public function testScanDirEmptyReturnsEmptyArray(): void {
        $this->assertSame([], FileSystem::scanDir($this->tmp, ['php']));
    }

    public function testScanDirFindsPhp(): void {
        file_put_contents($this->tmp . '/a.php', '<?php');
        $files = FileSystem::scanDir($this->tmp, ['php']);
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('a.php', $files[0]);
    }

    public function testScanDirFiltersByExtension(): void {
        file_put_contents($this->tmp . '/a.php', '<?php');
        file_put_contents($this->tmp . '/b.txt', 'text');
        file_put_contents($this->tmp . '/c.md', 'md');
        $files = FileSystem::scanDir($this->tmp, ['php']);
        $this->assertCount(1, $files);
    }

    public function testScanDirExcludesDirs(): void {
        mkdir($this->tmp . '/vendor', 0755, true);
        file_put_contents($this->tmp . '/vendor/a.php', '<?php');
        file_put_contents($this->tmp . '/b.php', '<?php');
        $files = FileSystem::scanDir($this->tmp, ['php'], ['vendor']);
        $this->assertCount(1, $files);
        $this->assertStringContainsString('b.php', $files[0]);
    }

    public function testScanDirRecursive(): void {
        mkdir($this->tmp . '/sub', 0755, true);
        mkdir($this->tmp . '/sub/deep', 0755, true);
        file_put_contents($this->tmp . '/sub/a.php', '<?php');
        file_put_contents($this->tmp . '/sub/deep/b.php', '<?php');
        $files = FileSystem::scanDir($this->tmp, ['php']);
        $this->assertCount(2, $files);
    }

    public function testScanDirMultipleExtensions(): void {
        file_put_contents($this->tmp . '/a.php', '<?php');
        file_put_contents($this->tmp . '/b.phtml', 'x');
        file_put_contents($this->tmp . '/c.txt', 'x');
        $files = FileSystem::scanDir($this->tmp, ['php', 'phtml']);
        $this->assertCount(2, $files);
    }
}
