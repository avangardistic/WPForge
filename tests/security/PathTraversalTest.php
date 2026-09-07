<?php

namespace WPForge\Tests\Security;

use WPForge\Filesystem\SecurityGuard;
use WP_UnitTestCase;

class PathTraversalTest extends WP_UnitTestCase
{
    private SecurityGuard $guard;

    public function setUp(): void
    {
        parent::setUp();
        $this->guard = new SecurityGuard(ABSPATH);
    }

    public function testRejectsParentDirectory(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->guard->validatePath('../../../../etc/passwd');
    }

    public function testRejectsRootOutside(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->guard->validatePath('/etc/passwd');
    }

    public function testAllowsValidPath(): void
    {
        $path = 'wp-content';
        $result = $this->guard->validatePath($path);
        $this->assertStringContainsString(ABSPATH, $result);
    }

    public function testNormalizesPath(): void
    {
        $path = 'wp-content//plugins//test/../file.php';
        $result = $this->guard->validatePath($path);
        $this->assertStringContainsString('wp-content/plugins/file.php', $result);
    }
}
