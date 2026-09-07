<?php

namespace WPForge\Filesystem;

/**
 * Filesystem scanner — recursively scan directories for files matching criteria.
 */
class Scanner
{
    private SecurityGuard $guard;

    public function __construct(string $root, array $allowedPaths = [])
    {
        $this->guard = new SecurityGuard($root, $allowedPaths);
    }

    /**
     * Recursively scan a directory for files.
     *
     * @param array{extensions?: string[], max_depth?: int, max_files?: int} $options
     */
    public function scan(string $path, array $options = []): array
    {
        $fullPath = $this->guard->validatePath($path);

        $defaults = ['extensions' => [], 'max_depth' => 10, 'max_files' => 1000];
        $options = wp_parse_args($options, $defaults);

        $results = [];
        $this->scanRecursive($fullPath, $path, $options, $results, 0);

        return [
            'files' => $results,
            'count' => count($results),
        ];
    }

    /**
     * Find files matching a pattern.
     */
    public function find(string $path, string $pattern): array
    {
        $fullPath = $this->guard->validatePath($path);
        $results = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (fnmatch($pattern, $file->getFilename())) {
                $results[] = [
                    'name'     => $file->getFilename(),
                    'path'     => ltrim(str_replace(wp_normalize_path(WPFORGE_PLUGIN_DIR), '', wp_normalize_path($file->getPathname())), '/'),
                    'type'     => $file->isDir() ? 'directory' : 'file',
                    'size'     => $file->getSize(),
                    'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                ];
            }
        }

        return $results;
    }

    /* ------------------------------------------------------------------ */

    private function scanRecursive(string $fullPath, string $relativePath, array $options, array &$results, int $depth): void
    {
        if ($depth > $options['max_depth'] || count($results) >= $options['max_files']) {
            return;
        }

        $items = @scandir($fullPath);
        if (!$items) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullItem = $fullPath . '/' . $item;
            $relItem  = $relativePath ? $relativePath . '/' . $item : $item;

            if (is_dir($fullItem)) {
                $this->scanRecursive($fullItem, $relItem, $options, $results, $depth + 1);
            } elseif (is_file($fullItem)) {
                if (!empty($options['extensions'])) {
                    $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                    if (!in_array($ext, $options['extensions'], true)) {
                        continue;
                    }
                }

                $results[] = [
                    'name'     => $item,
                    'path'     => $relItem,
                    'type'     => 'file',
                    'size'     => filesize($fullItem),
                    'modified' => date('Y-m-d H:i:s', filemtime($fullItem)),
                ];

                if (count($results) >= $options['max_files']) {
                    return;
                }
            }
        }
    }
}
