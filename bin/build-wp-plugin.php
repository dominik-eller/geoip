<?php

$pluginDir = 'wp-plugin';
$srcDir = 'src';
$buildDir = 'build';
$zipName = 'deller-geoip-plugin.zip';

if (!is_dir($pluginDir)) {
    echo "Fehler: $pluginDir Verzeichnis nicht gefunden.\n";
    exit(1);
}

if (!is_dir($srcDir)) {
    echo "Fehler: $srcDir Verzeichnis nicht gefunden.\n";
    exit(1);
}

if (!is_dir($buildDir)) {
    mkdir($buildDir, 0755, true);
}

$tmpDir = $buildDir . '/tmp_plugin';
if (is_dir($tmpDir)) {
    exec("rm -rf " . escapeshellarg($tmpDir));
}
mkdir($tmpDir, 0755, true);

// 1. Kopiere wp-plugin Inhalt
exec("cp -R " . escapeshellarg($pluginDir) . "/* " . escapeshellarg($tmpDir));

// 2. Erstelle src Verzeichnis im tmp Plugin
mkdir($tmpDir . '/src', 0755, true);

// 3. Kopiere src Dateien
exec("cp " . escapeshellarg($srcDir) . "/*.php " . escapeshellarg($tmpDir) . "/src/");

// 4. Erstelle ZIP
$zipFile = realpath($buildDir) . '/' . $zipName;
if (file_exists($zipFile)) {
    unlink($zipFile);
}

$currentDir = getcwd();
chdir($tmpDir);

// Wir wollen, dass der Inhalt des ZIPs im Ordner "geoip" liegt (Standard für WP Plugins beim Entpacken)
// Oder einfach die Dateien direkt, aber meistens ist ein Unterordner sauberer.
// Der Benutzer fragte nach "wp-plugin Ordner und src".
// Ich packe den Inhalt von $tmpDir in ein ZIP.

exec("zip -r " . escapeshellarg($zipFile) . " .");

chdir($currentDir);

// Cleanup
exec("rm -rf " . escapeshellarg($tmpDir));

echo "Erfolg: WordPress Plugin wurde erstellt: $buildDir/$zipName\n";
