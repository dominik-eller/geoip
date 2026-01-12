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

## WordPress Plugin

Dieses Paket enthält ein WordPress-Plugin für eine einfache Integration.

### Build (ZIP erstellen)
Da das Plugin die Kern-Bibliothek nutzt, aber ohne Composer lauffähig sein soll, muss es "gebaut" werden. Dabei werden die notwendigen Dateien in ein installierbares ZIP-Archiv gepackt.

```bash
composer build-wp-plugin
```
Das Ergebnis liegt unter `build/deller-geoip-plugin.zip`.

### Installation
1. Erstelle das ZIP-Archiv wie oben beschrieben.
2. Lade das ZIP-Archiv im WordPress-Adminbereich unter **Plugins -> Installieren -> Plugin hochladen** hoch.
3. Alternativ: Kopiere den Inhalt des `wp-plugin` Verzeichnisses manuell in `wp-content/plugins/geoip` und stelle sicher, dass die Dateien aus `src/` ebenfalls im `src/` Ordner des Plugins liegen.
4. Aktiviere das Plugin im WordPress-Adminbereich.

### Features
- **WP-CLI Command**: `wp geoip update` to refresh the GeoTargets data.
- **WP-CLI Command**: `wp geoip lookup <id>` to check a specific ID.
- **Helper Function**: Use `deller_geoip_lookup($id)` in your own plugins or themes.
- **Data Storage**: The CSV data is stored in your WordPress uploads directory under `wp-content/uploads/geoip-data/`.
- **Automatic Updates**: The plugin schedules a monthly WP-Cron event (`deller_geoip_update_event`) to keep the data fresh.

### Manual Cron Trigger
If you want to trigger the update via a real system cron job, you have several options:

#### 1. Via Standalone PHP script (Recommended for Plesk/Standard Hosting)
The plugin provides a dedicated script that can be called directly:
```bash
php wp-content/plugins/geoip/cron-update.php
```
This is the easiest way if you don't have WP-CLI installed.

#### 2. Via WP-CLI
```bash
wp geoip update
```

#### 3. Via Action Hook
Or hook into the action within your own code:
```php
do_action('deller_geoip_update_event');
```

### Example usage in code
```php
$data = deller_geoip_lookup(1004542);
if ($data) {
    echo "Location: " . $data['canonical_name'];
}
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
