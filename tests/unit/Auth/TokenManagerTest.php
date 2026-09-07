<?php

namespace WPForge\Tests\Unit\Auth;

use WPForge\Auth\TokenManager;
use WP_UnitTestCase;

class TokenManagerTest extends WP_UnitTestCase
{
    private TokenManager $tm;
    private int $userId;

    public function setUp(): void
    {
        parent::setUp();
        $this->tm = new TokenManager();
        $this->userId = $this->factory->user->create(['role' => 'administrator']);
    }

    public function testCreateToken(): void
    {
        $result = $this->tm->createToken($this->userId, 'Test token');
        $this->assertArrayHasKey('token_id', $result);
        $this->assertArrayHasKey('token_secret', $result);
        $this->assertArrayHasKey('full_token', $result);
    }

    public function testValidateToken(): void
    {
        $created = $this->tm->createToken($this->userId, 'Test');
        $user = $this->tm->validateToken($created['full_token']);
        $this->assertNotNull($user);
        $this->assertEquals($this->userId, $user->ID);
    }

    public function testInvalidToken(): void
    {
        $user = $this->tm->validateToken('invalid.token');
        $this->assertNull($user);
    }

    public function testRevokeToken(): void
    {
        $created = $this->tm->createToken($this->userId, 'Revoke me');
        $this->assertTrue($this->tm->revokeToken($created['token_id']));
        $this->assertNull($this->tm->validateToken($created['full_token']));
    }

    public function testListTokens(): void
    {
        $this->tm->createToken($this->userId, 'T1');
        $this->tm->createToken($this->userId, 'T2');
        $tokens = $this->tm->listTokens($this->userId);
        $this->assertCount(2, $tokens);
    }
}
