<?php

namespace Deller\GeoIP;

/**
 * Looks up Google Ads GeoTargets by criteria_id (aka loc_physical_ms).
 */
class GeoTargetsLookup
{
    private string $csvPath;

    /**
     * @param string $csvPath Absolute path to geotargets.csv
     */
    public function __construct(string $csvPath)
    {
        $this->csvPath = $csvPath;
    }

    /**
     * Finds a row by criteria_id and returns it as an associative array.
     * Returns null if not found.
     *
     * @return array<string,string>|null
     */
    public function findById(string|int $criteriaId): ?array
    {
        if (!is_file($this->csvPath)) {
            throw new \RuntimeException("CSV not found: {$this->csvPath}");
        }
        $fh = @fopen($this->csvPath, 'rb');
        if ($fh === false) {
            throw new \RuntimeException("Unable to open CSV: {$this->csvPath}");
        }
        // Skip header
        $header = fgetcsv($fh);
        $result = null;
        while (($row = fgetcsv($fh)) !== false) {
            if ((string)$row[0] === (string)$criteriaId) {
                $result = [
                    'criteria_id'    => $row[0] ?? '',
                    'name'           => $row[1] ?? '',
                    'canonical_name' => $row[2] ?? '',
                    'parent_id'      => $row[3] ?? '',
                    'country_code'   => $row[4] ?? '',
                    'target_type'    => $row[5] ?? '',
                    'status'         => $row[6] ?? '',
                ];
                break;
            }
        }
        fclose($fh);
        return $result;
    }
}
