<?php
/**
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
 * │  ──────────────────────────────────────────────────────────────────  │
 * │  File      : tests/Unit/HtmlReportTest.php
 * │  Purpose   : Unit tests for Report\HtmlReport::render
 * │  License   : Proprietary — all rights reserved                       │
 * └──────────────────────────────────────────────────────────────────────┘
 */

declare(strict_types=1);
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SatuRumah\Forensic\Report\HtmlReport;

final class HtmlReportTest extends TestCase {
    private string $out;

    protected function setUp(): void {
        $this->out = sys_get_temp_dir() . '/bona-html-' . bin2hex(random_bytes(4)) . '.html';
    }

    protected function tearDown(): void {
        if (is_file($this->out)) unlink($this->out);
    }

    public function testRenderCreatesFile(): void {
        $results = [['status' => 'PASS', 'name' => 'Demo', 'message' => 'ok']];
        HtmlReport::render($results, '/tmp/target', $this->out, '2.7.0');
        $this->assertFileExists($this->out);
    }

    public function testRenderContainsDoctypeAndTitle(): void {
        $results = [['status' => 'PASS', 'name' => 'Demo', 'message' => 'ok']];
        HtmlReport::render($results, '/tmp/target', $this->out, '2.7.0');
        $html = (string)file_get_contents($this->out);
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('Bona Forensic', $html);
        $this->assertStringContainsString('/tmp/target', $html);
    }

    public function testOverallStatusPASS(): void {
        $results = [['status' => 'PASS', 'name' => 'X', 'message' => 'ok']];
        HtmlReport::render($results, '/tmp/t', $this->out, '2.7.0');
        $html = (string)file_get_contents($this->out);
        $this->assertStringContainsString('PASS', $html);
    }

    public function testOverallStatusWARN(): void {
        $results = [
            ['status' => 'PASS', 'name' => 'X', 'message' => 'ok'],
            ['status' => 'WARN', 'name' => 'Y', 'message' => 'hmm'],
        ];
        HtmlReport::render($results, '/tmp/t', $this->out, '2.7.0');
        $html = (string)file_get_contents($this->out);
        $this->assertStringContainsString('WARN', $html);
    }

    public function testOverallStatusFAIL(): void {
        $results = [['status' => 'FAIL', 'name' => 'X', 'message' => 'bad']];
        HtmlReport::render($results, '/tmp/t', $this->out, '2.7.0');
        $html = (string)file_get_contents($this->out);
        $this->assertStringContainsString('FAIL', $html);
    }
}
