<?php

namespace WPForge\Tests\Security;

use WPForge\Filesystem\SecurityGuard;
use WPForge\Security\PathValidator;
use WP_UnitTestCase;

/**
 * Regression coverage for the filesystem path guards. Every entry in
 * blockedPaths() must be refused by both SecurityGuard (used by the /files/*
 * routes) and PathValidator.
 */
class PathTraversalTest extends WP_UnitTestCase
{
    private SecurityGuard $guard;
    private PathValidator $validator;

    public function setUp(): void
    {
        parent::setUp();
        $this->guard = new SecurityGuard(ABSPATH);
        $this->validator = new PathValidator(ABSPATH);
    }

    /**
     * @return array<string, array{0:string}>
     */
    public function blockedPaths(): array
    {
        return [
            'parent traversal'      => ['../../../../etc/passwd'],
            'absolute unix'         => ['/etc/passwd'],
            'interior traversal'    => ['wp-content/../../../../etc/passwd'],
            'wp-config'             => ['wp-config.php'],
            'wp-config leading /'   => ['/wp-config.php'],
            'wp-config via ..'      => ['wp-content/../wp-config.php'],
            'wp-config-sample'      => ['wp-config-sample.php'],
            'htaccess'              => ['.htaccess'],
            'dotenv'                => ['.env'],
            'null byte'             => ['evil' . chr(0) . '.php'],
            'control character'     => ['evil' . chr(7) . '.php'],
            'windows drive slash'   => ['C:/Windows/win.ini'],
            'windows drive back'    => ['C:' . chr(92) . 'Windows' . chr(92) . 'win.ini'],
            'alternate data stream' => ['file.php:stream'],
            'unc prefix'            => [chr(92) . chr(92) . 'server' . chr(92) . 'share'],
            'authority prefix'      => ['//evil.example/x'],
        ];
    }

    /**
     * @dataProvider blockedPaths
     */
    public function testSecurityGuardBlocks(string $path): void
    {
        $this->expectException(\RuntimeException::class);
        $this->guard->validatePath($path);
    }

    /**
     * @dataProvider blockedPaths
     */
    public function testPathValidatorBlocks(string $path): void
    {
        $this->expectException(\RuntimeException::class);
        $this->validator->validatePath($path);
    }

    public function testAllowsLegitimatePath(): void
    {
        $result = $this->guard->validatePath('wp-content');
        $this->assertStringContainsString('wp-content', $result);
    }

    public function testNormalizesInteriorDotSegments(): void
    {
        $result = $this->guard->validatePath('wp-content//plugins//test/../file.php');
        $this->assertStringContainsString('wp-content/plugins/file.php', str_replace(chr(92), '/', $result));
    }
}
