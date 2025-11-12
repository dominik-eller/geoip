<?php
require __DIR__ . '/../vendor/autoload.php';

use Deller\GeoIP\GeoTargetsLookup;

$csv = dirname(__DIR__) . '/data/geotargets.csv';
$loc = $_GET['loc'] ?? $_GET['loc_physical_ms'] ?? null;

header('Content-Type: application/json; charset=utf-8');
if (!$loc) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_parameter', 'param' => 'loc_physical_ms']);
    exit;
}

try {
    $lookup = new GeoTargetsLookup($csv);
    $row = $lookup->findById($loc);
    echo json_encode($row ?: ['error' => 'not_found', 'criteria_id' => (string)$loc], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'lookup_failed', 'message' => $e->getMessage()]);
}
