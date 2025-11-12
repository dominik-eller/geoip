<?php

declare(strict_types=1);

namespace Deller\GeoIP\Tests;

use Deller\GeoIP\GeoTargetsUpdater;
use PHPUnit\Framework\TestCase;

class GeoTargetsUpdaterTest extends TestCase
{
    private string $workDir;
    private string $fixtureCsv;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir() . '/geo_updater_' . uniqid();
        @mkdir($this->workDir, 0777, true);
        $this->fixtureCsv = __DIR__ . '/fixtures/geotargets_sample.csv';
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->workDir);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public function testUpdateWithDirectCsvDownload(): void
    {
        $fake = new class($this->workDir, $this->fixtureCsv) extends GeoTargetsUpdater {
            private string $fixture;
            public function __construct(string $targetDir, string $fixture)
            {
                parent::__construct($targetDir);
                $this->fixture = $fixture;
            }
            protected function getHtml(string $url): ?string
            {
                return '<a href="/data/geotargets.csv">download</a>';
            }
            protected function extractDownloadUrl(string $html): ?string
            {
                return '/data/geotargets.csv';
            }
            protected function downloadFile(string $url, string $path): bool
            {
                return copy($this->fixture, $path);
            }
        };

        $csv = $fake->update('http://irrelevant');
        $this->assertFileExists($csv);
        $this->assertSame($this->workDir . '/geotargets.csv', $csv);
        $data = file_get_contents($csv);
        $this->assertNotFalse($data);
        $this->assertStringContainsString('Germany', $data);
        $this->assertFileExists($this->workDir . '/last_update.txt');
    }

    public function testUpdateWithZipArchive(): void
    {
        // Create a temporary ZIP containing the fixture CSV
        $zipPath = $this->workDir . '/sample.zip';
        $csvNameInside = 'geotargets.csv';
        $zip = new \ZipArchive();
        $opened = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $this->assertTrue($opened === true);
        $zip->addFile($this->fixtureCsv, $csvNameInside);
        $zip->close();

        $fake = new class($this->workDir, $zipPath) extends GeoTargetsUpdater {
            private string $zipFixture;
            public function __construct(string $targetDir, string $zipFixture)
            {
                parent::__construct($targetDir);
                $this->zipFixture = $zipFixture;
            }
            protected function getHtml(string $url): ?string
            {
                return '<a href="/data/geotargets.zip">download</a>';
            }
            protected function extractDownloadUrl(string $html): ?string
            {
                return '/data/geotargets.zip';
            }
            protected function downloadFile(string $url, string $path): bool
            {
                return copy($this->zipFixture, $path);
            }
        };

        $csv = $fake->update('http://irrelevant');
        $this->assertFileExists($csv);
        $content = file_get_contents($csv);
        $this->assertNotFalse($content);
        $this->assertStringContainsString('Berlin', $content);
    }

    public function testThrowsWhenNoLinkFound(): void
    {
        $fake = new class($this->workDir) extends GeoTargetsUpdater {
            public function __construct(string $targetDir)
            {
                parent::__construct($targetDir);
            }
            protected function getHtml(string $url): ?string { return '<html>No link</html>'; }
            protected function extractDownloadUrl(string $html): ?string { return null; }
        };

        $this->expectException(\RuntimeException::class);
        $fake->update('http://irrelevant');
    }
}
