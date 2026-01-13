<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CLI Commands for GeoIP
 */
if (!class_exists('Deller_GeoIP_CLI')) {
    class Deller_GeoIP_CLI {

        /**
         * Updates the GeoTargets CSV file from Google.
         *
         * ## OPTIONS
         *
         * [--country=<country-code>]
         * : Filter by country code (e.g. DE).
         *
         * ## EXAMPLES
         *
         *     wp geoip update
         *     wp geoip update --country=DE
         *
         * @when after_wp_load
         */
        public function update($args, $assoc_args) {
            WP_CLI::log('Starting GeoIP update...');
            $country = $assoc_args['country'] ?? null;

            try {
                $updater = Deller_GeoIP::get_instance()->get_updater();
                if ($country) {
                    $updater->setCountryFilter($country);
                    WP_CLI::log("Filtering by country: $country");
                }
                $path = $updater->update();
                WP_CLI::success("GeoTargets updated successfully: $path");
            } catch (\Exception $e) {
                WP_CLI::error('Failed to update GeoTargets: ' . $e->getMessage());
            }
        }

        /**
         * Looks up a GeoTarget by ID.
         *
         * <id>
         * : The criteria ID to look up.
         *
         * ## EXAMPLES
         *
         *     wp geoip lookup 1004542
         *
         * @when after_wp_load
         */
        public function lookup($args, $assoc_args) {
            list($id) = $args;
            
            try {
                $result = Deller_GeoIP::get_instance()->get_lookup()->findById($id);
                if ($result) {
                    WP_CLI::table(array_keys($result), [$result]);
                } else {
                    WP_CLI::error("No GeoTarget found for ID: $id");
                }
            } catch (\Exception $e) {
                WP_CLI::error('Lookup failed: ' . $e->getMessage());
            }
        }
    }
}
