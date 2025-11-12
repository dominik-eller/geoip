<?php
require __DIR__ . '/../vendor/autoload.php';

use Deller\GeoIP\GeoTargetsLookup;

$csv = dirname(__DIR__) . '/data/geotargets.csv';
$loc = $argv[1] ?? null;

if (!$loc) {
    fwrite(STDERR, "Usage: php examples/lookup.php <criteria_id>\n");
    exit(2);
}

try {
    $lookup = new GeoTargetsLookup($csv);
    $row = $lookup->findById($loc);
    echo json_encode($row ?: ['error' => 'not_found', 'criteria_id' => (string)$loc], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Lookup failed: {$e->getMessage()}\n");
    exit(3);
}
