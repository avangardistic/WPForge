<?php
/**
 * WPForge build/packaging script.
 */
$pluginDir = dirname(__DIR__, 2) . '/wordpress/wpforge';
$outputDir = dirname(__DIR__, 2) . '/build';
$version = '1.0.0';

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$zipFile = $outputDir . "/wpforge-{$version}.zip";

echo "Packaging WPForge v{$version}...\n";

$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($pluginDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        $relativePath = str_replace($pluginDir . '/', '', $file->getPathname());
        if (str_contains($relativePath, 'tests/') || str_contains($relativePath, '.git')) {
            continue;
        }
        $zip->addFile($file->getPathname(), "wpforge/{$relativePath}");
    }

    $zip->close();
    echo "Created: {$zipFile}\n";
    echo "Size: " . number_format(filesize($zipFile)) . " bytes\n";
} else {
    echo "Failed to create zip file.\n";
    exit(1);
}
