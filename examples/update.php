<?php
require __DIR__ . '/../vendor/autoload.php';

use Deller\GeoIP\GeoTargetsUpdater;

$dir = dirname(__DIR__) . '/data';
$updater = new GeoTargetsUpdater($dir);

try {
    $csv = $updater->update();
    echo "Saved CSV to: {$csv}\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Update failed: {$e->getMessage()}\n");
    exit(1);
}
