# Deller/GeoIP — Google Ads GeoTargets as a Composer package

Small PHP 8.4 library and CLI to download the official Google Ads GeoTargets list and look up entries by `criteria_id` (aka `loc_physical_ms`).

- Source page: https://developers.google.com/google-ads/api/data/geotargets
- Stores a local `geotargets.csv`
- Lookup by `criteria_id` and return a JSON-compatible array
- Includes CLI tools for update and lookup

## Installation

```
composer require dominik-eller/geoip
```

Requirements:
- PHP 8.4+
- ext-zip and ext-json

## Quick start (library)

```php
use Deller\GeoIP\GeoTargetsUpdater;
use Deller\GeoIP\GeoTargetsLookup;

// 1) Download/refresh CSV (once or via cron)
$targetDir = __DIR__ . '/var/geodata';
$updater = new GeoTargetsUpdater($targetDir);
$csvPath = $updater->update(); // returns /absolute/path/to/geotargets.csv

// 2) Lookup by criteria_id (aka loc_physical_ms)
$lookup = new GeoTargetsLookup($csvPath);
$result = $lookup->findById(2267);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result ?: ['error' => 'not_found'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
```

Returned structure on success:

```json
{
  "criteria_id": "2267",
  "name": "Germany",
  "canonical_name": "Germany",
  "country_code": "DE",
  "target_type": "Country",
  "status": "Active"
}
```

If not found:

```json
{"error": "not_found", "criteria_id": "<id>"}
```

## CLI tools

Two executables are provided via Composer bin:

- `geotargets-update` — downloads the latest CSV
- `geotargets-lookup` — looks up an ID and prints JSON

Examples:

```
# Download to ./data by default
vendor/bin/geotargets-update

# Set custom directory
GEOTARGETS_DIR=/var/data/geo vendor/bin/geotargets-update

# Lookup by ID
vendor/bin/geotargets-lookup 2267

# Or via env var
LOC=2267 vendor/bin/geotargets-lookup

# Custom CSV path
GEOTARGETS_CSV=/var/data/geo/geotargets.csv vendor/bin/geotargets-lookup 2267
```

## Web endpoint example

Create `public/geotarget.php` in your app:

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use Deller\GeoIP\GeoTargetsLookup;

$csv = __DIR__ . '/../var/geodata/geotargets.csv';
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
```

## Cron suggestion

Update monthly (Google updates roughly monthly):

```
0 3 1 * * /usr/bin/env php /path/to/your/app/vendor/bin/geotargets-update >> /var/log/geotargets_update.log 2>&1
```

## JSON response spec

- Success object:
  - `criteria_id` string
  - `name` string
  - `canonical_name` string
  - `country_code` string (2-letter)
  - `target_type` string (e.g., Country, City, …)
  - `status` string (Active/Deprecated)
- Error object:
  - `error` one of `missing_parameter`, `not_found`, `lookup_failed`
  - optional `criteria_id` and `message`

## Notes

- The downloader scrapes the official docs page to find the current `geotargets.zip`/`geotargets.csv` link. If Google changes the page structure, override the source with `GEOTARGETS_SOURCE` or pass the URL to `GeoTargetsUpdater::update($url)`.
- The library writes an optional `last_update.txt` file next to the CSV.

## Tests & Examples

- Tests use PHPUnit and run offline with fixtures.
- Run tests:
  ```
  composer install
  composer test
  ```
- Example scripts are in `examples/`:
  - `examples/update.php`
  - `examples/lookup.php`
  - `examples/web-endpoint.php` (serve with `php -S 127.0.0.1:8080 -t examples`)

## License

MIT License. See `LICENSE`.
