<?php

namespace WPForge\Auth;

use WP_User;

/**
 * WPForge token manager — create, validate, revoke, and list API tokens.
 */
class TokenManager
{
    private string $tableName;

    public function __construct()
    {
        global $wpdb;
        $this->tableName = $wpdb->prefix . 'wpforge_tokens';
    }

    /**
     * Create a new API token for a user.
     *
     * @return array{token_id: string, token_secret: string, full_token: string, created_at: string, expires_at: string}
     */
    public function createToken(int $userId, string $description = ''): array
    {
        $tokenId     = $this->generateTokenId();
        $tokenSecret = $this->generateTokenSecret();
        $tokenHash   = wp_hash_password($tokenSecret);

        global $wpdb;
        $result = $wpdb->insert(
            $this->tableName,
            [
                'token_hash'  => $tokenHash,
                'token_id'    => $tokenId,
                'user_id'     => $userId,
                'description' => sanitize_text_field($description),
                'created_at'  => current_time('mysql'),
                'expires_at'  => gmdate('Y-m-d H:i:s', strtotime('+1 year')),
            ]
        );

        if (!$result) {
            throw new \RuntimeException('Failed to create token: ' . $wpdb->last_error);
        }

        return [
            'token_id'    => $tokenId,
            'token_secret' => $tokenSecret,
            'full_token'  => $tokenId . '.' . $tokenSecret,
            'created_at'  => current_time('mysql'),
            'expires_at'  => gmdate('Y-m-d H:i:s', strtotime('+1 year')),
        ];
    }

    /**
     * Validate a full token string (token_id.token_secret).
     */
    public function validateToken(string $fullToken): ?WP_User
    {
        $parts = explode('.', $fullToken, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$tokenId, $tokenSecret] = $parts;

        global $wpdb;
        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->tableName} WHERE token_id = %s AND revoked = 0 AND (expires_at IS NULL OR expires_at > NOW())",
                $tokenId
            )
        );

        if (!$record) {
            return null;
        }

        if (!wp_check_password($tokenSecret, $record->token_hash)) {
            return null;
        }

        // Update last_used timestamp.
        $wpdb->update(
            $this->tableName,
            ['last_used' => current_time('mysql')],
            ['id' => $record->id]
        );

        return get_user_by('id', (int) $record->user_id) ?: null;
    }

    /**
     * Get a token record by its ID (for authorization checks).
     */
    public function getTokenById(string $tokenId): ?object
    {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->tableName} WHERE token_id = %s",
                $tokenId
            )
        );
    }

    /**
     * Revoke a token by its token_id.
     */
    public function revokeToken(string $tokenId): bool
    {
        global $wpdb;
        $result = $wpdb->update(
            $this->tableName,
            ['revoked' => 1],
            ['token_id' => $tokenId]
        );

        return $result !== false && $result > 0;
    }

    /**
     * List all tokens (metadata only) for a user.
     */
    public function listTokens(int $userId): array
    {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT token_id, description, created_at, last_used, expires_at, revoked FROM {$this->tableName} WHERE user_id = %d ORDER BY created_at DESC",
                $userId
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Revoke all tokens for a user.
     */
    public function revokeAllTokens(int $userId): bool
    {
        global $wpdb;
        $result = $wpdb->update(
            $this->tableName,
            ['revoked' => 1],
            ['user_id' => $userId]
        );

        return $result !== false;
    }

    /* ------------------------------------------------------------------ */
    /*  Private helpers                                                    */
    /* ------------------------------------------------------------------ */

    private function generateTokenId(): string
    {
        return 'wf_' . bin2hex(random_bytes(16));
    }

    private function generateTokenSecret(): string
    {
        return bin2hex(random_bytes(32));
    }
}
