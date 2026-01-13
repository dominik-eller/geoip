<?php
/**
 * Plugin Name: Deller's GeoIP for Google Ads
 * Description: Provides Google Ads GeoTargets lookup and update functionality.
 * Version: 1.0.0
 * Author: Dominik Eller
 * License: MIT
 */

if (!defined('ABSPATH')) {
    exit;
}

// Autoloading: Since this plugin is part of the repository, 
// we assume vendor/autoload.php is in the root or plugin's vendor.
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    // Manual autoloader for environments without Composer
    spl_autoload_register(function ($class) {
        $prefix = 'Deller\\GeoIP\\';
        $base_dir = __DIR__ . '/src/';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    });
}

use Deller\GeoIP\GeoTargetsLookup;
use Deller\GeoIP\GeoTargetsUpdater;

/**
 * Singleton class for GeoIP Integration
 */
if (!class_exists('Deller_GeoIP')) {
    class Deller_GeoIP {
        private static $instance = null;
        private $lookup = null;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            if (defined('WP_CLI') && WP_CLI) {
                require_once __DIR__ . '/inc/class-geoip-cli.php';
                WP_CLI::add_command('geoip', 'Deller_GeoIP_CLI');
            }

            add_action('deller_geoip_update_event', [$this, 'run_update']);
        }

        /**
         * Run the update process
         * 
         * @param string|null $country Optional country code to filter (e.g. 'DE')
         */
        public function run_update($country = null) {
            try {
                $updater = $this->get_updater();
                if ($country) {
                    $updater->setCountryFilter($country);
                }
                $updater->update();
            } catch (\Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('GeoIP Update Error: ' . $e->getMessage());
                }
            }
        }

        /**
         * Get the data directory for GeoIP files
         */
        public function get_data_dir() {
            $upload_dir = wp_upload_dir();
            $dir = $upload_dir['basedir'] . '/geoip-data';
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }
            return $dir;
        }

        /**
         * Get the CSV path
         */
        public function get_csv_path() {
            return $this->get_data_dir() . '/geotargets.csv';
        }

        /**
         * Get the updater instance
         */
        public function get_updater() {
            return new GeoTargetsUpdater($this->get_data_dir());
        }

        /**
         * Get the lookup instance
         */
        public function get_lookup() {
            if ($this->lookup === null) {
                $this->lookup = new GeoTargetsLookup($this->get_csv_path());
            }
            return $this->lookup;
        }
    }
}

/**
 * Helper function for other plugins
 */
if (!function_exists('deller_geoip_lookup')) {
    function deller_geoip_lookup($criteria_id) {
        try {
            return Deller_GeoIP::get_instance()->get_lookup()->findById($criteria_id);
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('GeoIP Lookup Error: ' . $e->getMessage());
            }
            return null;
        }
    }
}

register_activation_hook(__FILE__, function() {
    if (!wp_next_scheduled('deller_geoip_update_event')) {
        wp_schedule_event(time(), 'monthly', 'deller_geoip_update_event');
    }
});

register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('deller_geoip_update_event');
});

/**
 * Initialize the plugin
 */
add_action('plugins_loaded', function() {
    Deller_GeoIP::get_instance();
});
