<?php

namespace WPForge\Tests\Unit\Filesystem;

use PHPUnit\Framework\TestCase;
use WPForge\Filesystem\SecurityGuard;

/**
 * Unit tests for SecurityGuard filesystem protection.
 */
class SecurityGuardTest extends TestCase
{
    private SecurityGuard $guard;
    private string $testRoot;

    public function setUp(): void
    {
        parent::setUp();
        $this->testRoot = sys_get_temp_dir() . '/wpforge-test-root-' . uniqid();
        mkdir($this->testRoot, 0755, true);
        // Create test subdirectories
        mkdir($this->testRoot . '/subdir', 0755, true);
        file_put_contents($this->testRoot . '/test.txt', 'test content');
        
        // Mock wp_normalize_path if not available
        if (!function_exists('wp_normalize_path')) {
            eval('function wp_normalize_path($path) { return str_replace("\\\\", "/", $path); }');
        }
        
        $this->guard = new SecurityGuard($this->testRoot);
    }

    public function tearDown(): void
    {
        // Cleanup
        $this->deleteDir($this->testRoot);
        parent::tearDown();
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * @return array<string, array{0:string}>
     */
    public function blockedPaths(): array
    {
        return [
            'parent traversal simple'      => ['../etc/passwd'],
            'parent traversal deep'        => ['../../../../etc/passwd'],
            'absolute unix etc'            => ['/etc/passwd'],
            'interior traversal'           => ['subdir/../../../../etc/passwd'],
            'wp-config direct'             => ['wp-config.php'],
            'wp-config with leading slash' => ['/wp-config.php'],
            'wp-config via traversal'      => ['subdir/../wp-config.php'],
            'wp-config-sample'             => ['wp-config-sample.php'],
            'htaccess'                     => ['.htaccess'],
            'dotenv'                       => ['.env'],
            'null byte injection'          => ['evil' . chr(0) . '.php'],
            'control character'            => ['evil' . chr(7) . '.php'],
            'windows drive letter'         => ['C:/Windows/win.ini'],
            'UNC path prefix'              => ['//server/share'],
        ];
    }

    /**
     * @dataProvider blockedPaths
     */
    public function testBlocksPathTraversalAndSensitiveFiles(string $path): void
    {
        $this->expectException(\RuntimeException::class);
        $this->guard->validatePath($path);
    }

    public function testAllowsValidSubdirectoryPath(): void
    {
        $result = $this->guard->validatePath('subdir');
        $this->assertStringContainsString('subdir', str_replace('\\', '/', $result));
        $this->assertStringContainsString($this->testRoot, str_replace('\\', '/', $result));
    }

    public function testAllowsValidFilePath(): void
    {
        $result = $this->guard->validatePath('test.txt');
        $this->assertStringContainsString('test.txt', str_replace('\\', '/', $result));
    }

    public function testNormalizesPathWithMultipleSlashes(): void
    {
        $result = $this->guard->validatePath('subdir//nested///file.php');
        $this->assertStringContainsString('subdir/nested/file.php', str_replace('\\', '/', $result));
    }

    public function testResolvesDotSegments(): void
    {
        $result = $this->guard->validatePath('subdir/./nested/../file.php');
        $this->assertStringContainsString('subdir/file.php', str_replace('\\', '/', $result));
    }

    public function testBlocksEscapeViaTraversal(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Path escapes the allowed root');
        $this->guard->validatePath('../outside-file.txt');
    }

    public function testBlocksEmptyPath(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->guard->validatePath('');
    }
}
