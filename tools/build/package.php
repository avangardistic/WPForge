<?php
/**
 * WPForge build/packaging script.
 *
 * Produces build/wpforge-<version>.zip with a single top-level `wpforge/`
 * directory, which is the layout the WordPress plugin installer expects.
 */
$rootDir   = dirname(__DIR__, 2);
$pluginDir = $rootDir . '/wordpress/wpforge';
$outputDir = $rootDir . '/build';

// Take the version from the plugin header so the archive can never drift from it.
$mainFile = $pluginDir . '/wpforge.php';
if (!is_file($mainFile)) {
    fwrite(STDERR, "Plugin file not found: {$mainFile}\n");
    exit(1);
}

if (!preg_match('/^\s*\*\s*Version:\s*(.+)$/m', file_get_contents($mainFile), $m)) {
    fwrite(STDERR, "Could not read the Version header from wpforge.php\n");
    exit(1);
}
$version = trim($m[1]);

if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Could not create {$outputDir}\n");
    exit(1);
}

$zipFile = $outputDir . "/wpforge-{$version}.zip";

echo "Packaging WPForge v{$version}...\n";

$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Failed to create {$zipFile}\n");
    exit(1);
}

// Normalise separators so this works on Windows as well as POSIX.
$normalise = static fn (string $path): string => str_replace(DIRECTORY_SEPARATOR, '/', $path);
$base      = rtrim($normalise($pluginDir), '/') . '/';

$excludedDirs  = ['tests', 'node_modules', '.git'];
$excludedFiles = ['.DS_Store', 'Thumbs.db'];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($pluginDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$count = 0;
foreach ($iterator as $file) {
    $absolute = $normalise($file->getPathname());

    if (strpos($absolute, $base) !== 0) {
        continue;
    }
    $relative = substr($absolute, strlen($base));

    $segments = explode('/', $relative);
    if (array_intersect($segments, $excludedDirs) !== []) {
        continue;
    }
    if (in_array($file->getFilename(), $excludedFiles, true)) {
        continue;
    }

    $zip->addFile($file->getPathname(), "wpforge/{$relative}");
    $count++;
}

$zip->close();

echo "Created: {$zipFile}\n";
echo "Files:   {$count}\n";
echo "Size:    " . number_format(filesize($zipFile)) . " bytes\n";
