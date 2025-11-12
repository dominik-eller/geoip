<?php

declare(strict_types=1);

namespace Deller\GeoIP\Tests;

use Deller\GeoIP\GeoTargetsLookup;
use PHPUnit\Framework\TestCase;

class GeoTargetsLookupTest extends TestCase
{
    private string $tmpCsv;

    protected function setUp(): void
    {
        $this->tmpCsv = sys_get_temp_dir() . '/geotargets_sample_' . uniqid() . '.csv';
        $fixture = __DIR__ . '/fixtures/geotargets_sample.csv';
        copy($fixture, $this->tmpCsv);
    }

    protected function tearDown(): void
    {
        if (is_file($this->tmpCsv)) {
            @unlink($this->tmpCsv);
        }
    }

    public function testFindByIdReturnsRowWhenFound(): void
    {
        $lookup = new GeoTargetsLookup($this->tmpCsv);
        $row = $lookup->findById(2267);
        $this->assertIsArray($row);
        $this->assertSame('2267', $row['criteria_id']);
        $this->assertSame('Germany', $row['name']);
        $this->assertSame('DE', $row['country_code']);
        $this->assertSame('Country', $row['target_type']);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $lookup = new GeoTargetsLookup($this->tmpCsv);
        $row = $lookup->findById('99999999');
        $this->assertNull($row);
    }

    public function testThrowsWhenCsvMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $lookup = new GeoTargetsLookup(__DIR__ . '/nope.csv');
        $lookup->findById(1);
    }
}
