<?php

namespace Deller\GeoIP;

use ZipArchive;

/**
 * Fetches and stores the latest Google Ads GeoTargets CSV.
 *
 * Default source page:
 *   https://developers.google.com/google-ads/api/data/geotargets
 *
 * This class downloads the latest CSV (or ZIP containing CSV) and saves it to
 * a target directory (default: project_root/data/geotargets.csv).
 */
class GeoTargetsUpdater
{
    public const DEFAULT_SOURCE_PAGE = 'https://developers.google.com/google-ads/api/data/geotargets';

    private string $targetDir;
    private string $csvFilename;
    private ?string $countryFilter = null;

    /**
     * @param string $targetDir Directory where CSV will be stored.
     * @param string $csvFilename Target CSV filename (default geotargets.csv)
     */
    public function __construct(string $targetDir, string $csvFilename = 'geotargets.csv')
    {
        $this->targetDir = rtrim($targetDir, '/');
        $this->csvFilename = $csvFilename;
    }

    /**
     * Sets a country code to filter the CSV (e.g., 'DE').
     */
    public function setCountryFilter(?string $countryCode): self
    {
        $this->countryFilter = $countryCode ? strtoupper($countryCode) : null;
        return $this;
    }

    /**
     * Downloads the latest GeoTargets CSV (or ZIP) and stores it.
     *
     * @param string $sourcePage Optional override for the Google docs page.
     * @return string Absolute path to the stored CSV file.
     * @throws \RuntimeException when downloading or extraction fails.
     */
    public function update(string $sourcePage = self::DEFAULT_SOURCE_PAGE): string
    {
        if (!is_dir($this->targetDir)) {
            if (!@mkdir($this->targetDir, 0755, true) && !is_dir($this->targetDir)) {
                throw new \RuntimeException("Failed to create target directory: {$this->targetDir}");
            }
        }

        $html = $this->getHtml($sourcePage);
        if ($html === null) {
            throw new \RuntimeException("Failed to load source page: $sourcePage");
        }

        $downloadUrl = $this->extractDownloadUrl($html);
        if (!$downloadUrl) {
            throw new \RuntimeException("No download link found on: $sourcePage");
        }

        if (!preg_match('/^https?:\/\//', $downloadUrl)) {
            // complete relative URL
            $downloadUrl = 'https://developers.google.com' . $downloadUrl;
        }

        $tmpDir = sys_get_temp_dir() . '/geo_update';
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0755, true);
        }
        $ext = pathinfo(parse_url($downloadUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'dat';
        $tmpFile = $tmpDir . '/geotargets_download.' . $ext;

        $ok = $this->downloadFile($downloadUrl, $tmpFile);
        if (!$ok) {
            throw new \RuntimeException("Failed downloading $downloadUrl");
        }

        $csvPath = $this->getCsvPath();

        if (preg_match('/\.zip$/i', $tmpFile)) {
            $zip = new ZipArchive();
            if ($zip->open($tmpFile) !== true) {
                @unlink($tmpFile);
                throw new \RuntimeException('Failed to open ZIP archive.');
            }
            $extracted = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name !== false && preg_match('/\.csv$/i', $name)) {
                    $stream = $zip->getStream($name);
                    if ($stream === false) {
                        continue;
                    }
                    $this->saveCsvStream($stream, $csvPath);
                    fclose($stream);
                    $extracted = true;
                    break;
                }
            }
            $zip->close();
            @unlink($tmpFile);
            if (!$extracted) {
                throw new \RuntimeException('No CSV file found inside the ZIP archive.');
            }
        } else {
            $stream = @fopen($tmpFile, 'rb');
            if ($stream === false) {
                @unlink($tmpFile);
                throw new \RuntimeException("Failed to open downloaded file $tmpFile");
            }
            $this->saveCsvStream($stream, $csvPath);
            fclose($stream);
            @unlink($tmpFile);
        }

        // Write last_update marker
        @file_put_contents($this->targetDir . '/last_update.txt', date('Y-m-d H:i:s'));

        return $csvPath;
    }

    public function getCsvPath(): string
    {
        return $this->targetDir . '/' . $this->csvFilename;
    }

    /**
     * Internal helper to save CSV stream with optional filtering.
     *
     * @param resource $inputStream
     * @param string $csvPath
     * @throws \RuntimeException
     */
    protected function saveCsvStream($inputStream, string $csvPath): void
    {
        $out = @fopen($csvPath, 'wb');
        if ($out === false) {
            throw new \RuntimeException("Failed to write CSV to $csvPath");
        }

        if ($this->countryFilter === null) {
            stream_copy_to_stream($inputStream, $out);
        } else {
            // Read header
            $header = fgetcsv($inputStream);
            if ($header !== false) {
                fputcsv($out, $header);
                // The country code is usually at index 4 in Google Ads CSV
                // Format: "Criteria ID","Name","Canonical Name","Parent ID","Country Code","Target Type","Status"
                while (($row = fgetcsv($inputStream)) !== false) {
                    if (isset($row[4]) && strtoupper($row[4]) === $this->countryFilter) {
                        fputcsv($out, $row);
                    }
                }
            }
        }
        fclose($out);
    }

    protected function getHtml(string $url): ?string
    {
        $opts = [
            'http' => [
                'timeout' => 15,
                'header'  => "User-Agent: Dominik-GeoIP-Updater/1.0\r\n"
            ]
        ];
        $result = @file_get_contents($url, false, stream_context_create($opts));
        return $result === false ? null : $result;
    }

    protected function downloadFile(string $url, string $path): bool
    {
        $opts = [
            'http' => [
                'timeout' => 120,
                'header'  => "User-Agent: Dominik-GeoIP-Updater/1.0\r\n"
            ]
        ];
        $data = @file_get_contents($url, false, stream_context_create($opts));
        if ($data === false) {
            return false;
        }
        if (@file_put_contents($path, $data) === false) {
            return false;
        }
        return file_exists($path) && filesize($path) > 100_000; // sanity check (~100KB+)
    }

    protected function extractDownloadUrl(string $html): ?string
    {
        // Search for link containing geotargets.zip or geotargets.csv
        if (preg_match('/href="([^"]+(geotargets.*?\.(?:zip|csv)))"/i', $html, $m)) {
            return html_entity_decode($m[1]);
        }
        return null;
    }
}
