<?php

namespace WPForge\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPForge\Logging\Logger;

/**
 * Base API handler for WPForge routes
 */
abstract class BaseRoutes
{
    protected string $namespace = 'wpforge/v1';
    protected Logger $logger;

    public function __construct()
    {
        $this->logger = new Logger();
    }

    /**
     * Register all routes
     */
    abstract public static function register(): void;

    /**
     * Generate a unique request ID
     */
    protected function generateRequestId(): string
    {
        return sprintf(
            '%s-%s',
            date('YmdHis'),
            bin2hex(random_bytes(8))
        );
    }

    /**
     * Create a success response
     */
    protected function successResponse(mixed $data, int $status = 200, ?string $request_id = null): WP_REST_Response
    {
        if (null === $request_id) {
            $request_id = $this->generateRequestId();
        }

        return new WP_REST_Response([
            'success' => true,
            'request_id' => $request_id,
            'data' => $data,
        ], $status);
    }

    /**
     * Create an error response
     */
    protected function errorResponse(string $code, string $message, int $status = 400, ?string $request_id = null): WP_REST_Response
    {
        if (null === $request_id) {
            $request_id = $this->generateRequestId();
        }

        return new WP_REST_Response([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'request_id' => $request_id,
            ],
        ], $status);
    }

    /**
     * Check authentication
     */
    protected function checkAuth(): bool|WP_Error
    {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'wpforge_unauthenticated',
                'Authentication required.',
                ['status' => 401]
            );
        }
        return true;
    }

    /**
     * Check capability
     */
    protected function checkCapability(string $capability): bool|WP_Error
    {
        if (!current_user_can($capability)) {
            return new WP_Error(
                'wpforge_insufficient_permissions',
                sprintf('Required capability: %s', $capability),
                ['status' => 403]
            );
        }
        return true;
    }

    /**
     * Log a mutation
     */
    protected function logMutation(string $operation, string $target, bool $success, int $status, ?string $error_code = null, array $metadata = []): void
    {
        $metadata['request_id'] = $metadata['request_id'] ?? $this->generateRequestId();
        $this->logger->log($operation, $target, $success, $status, $error_code, $metadata);
    }
}
