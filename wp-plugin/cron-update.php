<?php
/**
 * Standalone Cron Trigger for Deller GeoIP Update
 *
 * This script can be called directly via PHP CLI to trigger the GeoIP update process.
 * It attempts to find and load wp-load.php to access WordPress functionality.
 *
 * Usage: php cron-update.php [COUNTRY_CODE]
 * Example: php cron-update.php DE
 */

// Prevent web access for security
if (php_sapi_name() !== 'cli') {
    die('This script can only be run via the command line.');
}

// Find wp-load.php
$wp_load_path = '';
$current_dir = __DIR__;

// Search upwards for wp-load.php (usually 2-3 levels up from wp-content/plugins/geoip)
for ($i = 0; $i < 5; $i++) {
    if (file_exists($current_dir . '/wp-load.php')) {
        $wp_load_path = $current_dir . '/wp-load.php';
        break;
    }
    $current_dir = dirname($current_dir);
}

if (!$wp_load_path) {
    echo "Error: wp-load.php not found. Please ensure this script is located within a WordPress installation.\n";
    exit(1);
}

// Load WordPress
require_once $wp_load_path;

// Check if the plugin class exists
if (!class_exists('Deller_GeoIP')) {
    echo "Error: Deller_GeoIP class not found. Is the plugin active?\n";
    exit(1);
}

echo "Starting GeoIP update via standalone cron script...\n";

$country_filter = $argv[1] ?? null;

try {
    $geoip = Deller_GeoIP::get_instance();
    $updater = $geoip->get_updater();
    if ($country_filter) {
        echo "Filtering by country: $country_filter\n";
        $updater->setCountryFilter($country_filter);
    }
    $path = $updater->update();
    echo "Success: GeoTargets updated successfully: $path\n";
} catch (\Exception $e) {
    echo "Error: Failed to update GeoTargets: " . $e->getMessage() . "\n";
    exit(1);
}
