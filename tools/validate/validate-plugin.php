<?php

/**
 * WPForge plugin validation script.
 */

echo "Validating WPForge plugin...\n\n";

$pluginDir = dirname(__DIR__, 2) . '/wordpress/wpforge';
$errors = [];
$warnings = [];

// Check main plugin file
$mainFile = $pluginDir . '/wpforge.php';
if (!file_exists($mainFile)) {
    $errors[] = "Main plugin file missing: wpforge.php";
} else {
    $content = file_get_contents($mainFile);
    if (strpos($content, 'Plugin Name:') === false) {
        $errors[] = "Missing Plugin Name header";
    }
    if (strpos($content, 'Version:') === false) {
        $errors[] = "Missing Version header";
    }
    echo "✓ Main plugin file exists\n";
}

// Check required directories
$requiredDirs = ['src', 'routes', 'config'];
foreach ($requiredDirs as $dir) {
    if (is_dir($pluginDir . '/' . $dir)) {
        echo "✓ Directory: {$dir}/\n";
    } else {
        $errors[] = "Missing directory: {$dir}";
    }
}

// Check required source files
$requiredFiles = [
    'src/Core/Config.php',
    'src/Core/Container.php',
    'src/Core/RequestID.php',
    'src/API/Router.php',
    'src/API/Response.php',
    'src/API/Controller.php',
    'src/Auth/Authenticator.php',
    'src/Auth/TokenManager.php',
    'src/Auth/CapabilityChecker.php',
    'src/Security/Validator.php',
    'src/Security/PathValidator.php',
    'src/Filesystem/Manager.php',
    'src/Filesystem/SecurityGuard.php',
    'src/Database/Inspector.php',
    'src/Backup/Manager.php',
    'src/Diagnostics/HealthReporter.php',
    'src/Logging/Logger.php',
    'routes/system.php',
    'routes/content.php',
    'routes/elementor.php',
    'routes/filesystem.php',
    'routes/database.php',
    'routes/backup.php',
    'uninstall.php',
];

foreach ($requiredFiles as $file) {
    if (file_exists($pluginDir . '/' . $file)) {
        echo "✓ {$file}\n";
    } else {
        $errors[] = "Missing file: {$file}";
    }
}

// Check no TODO/placeholder in source files
$srcIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($pluginDir . '/src', RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($srcIterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $content  = file_get_contents($file->getPathname());
    $relative = str_replace($pluginDir . DIRECTORY_SEPARATOR, '', $file->getPathname());

    if (preg_match('/\b(TODO|FIXME|XXX)\b/', $content, $m)) {
        $errors[] = "{$m[1]} left in source: {$relative}";
    }

    if (strpos($content, 'var_dump(') !== false || strpos($content, 'print_r(') !== false) {
        $warnings[] = "Debug output call in {$relative}";
    }
}

echo "\n";

// Check the declared version agrees everywhere it appears
$mainContent = file_exists($mainFile) ? file_get_contents($mainFile) : '';
preg_match('/^\s*\*\s*Version:\s*(.+)$/m', $mainContent, $headerVersion);
preg_match('/WPFORGE_VERSION\x27,\s*\x27([^\x27]+)\x27/', $mainContent, $constVersion);

$stableTag = [];
$readmeTxt = $pluginDir . '/readme.txt';
if (file_exists($readmeTxt)) {
    preg_match('/^Stable tag:\s*(.+)$/m', file_get_contents($readmeTxt), $stableTag);
}

$versions = array_filter([
    'plugin header'         => isset($headerVersion[1]) ? trim($headerVersion[1]) : null,
    'WPFORGE_VERSION'       => $constVersion[1] ?? null,
    'readme.txt stable tag' => isset($stableTag[1]) ? trim($stableTag[1]) : null,
]);

if (count(array_unique($versions)) > 1) {
    $errors[] = 'Version mismatch: ' . json_encode($versions);
} elseif ($versions !== []) {
    echo "OK Version consistent: " . reset($versions) . "\n";
}

// Report
echo "\n";

foreach ($warnings as $warning) {
    echo "!  {$warning}\n";
}

if ($errors !== []) {
    echo "\n" . count($errors) . " error(s):\n";
    foreach ($errors as $error) {
        echo "   x {$error}\n";
    }
    exit(1);
}

echo "\nValidation passed" . ($warnings !== [] ? ' with ' . count($warnings) . ' warning(s)' : '') . ".\n";
exit(0);
