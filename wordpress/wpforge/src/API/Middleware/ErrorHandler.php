<?php
namespace WPForge\API\Middleware;

use WPForge\Logging\Logger;
use WPForge\Core\RequestID;

/**
 * Global error handler — catches exceptions thrown by route callbacks
 * and converts them to structured JSON error responses.
 */
class ErrorHandler
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Register as a WordPress exception handler.
     */
    public function register(): void
    {
        set_exception_handler([$this, 'handle']);
    }

    /**
     * Handle an uncaught throwable.
     */
    public function handle(\Throwable $e): void
    {
        $requestId = RequestID::get();

        $this->logger->logError(
            'uncaught_exception',
            $e->getFile() . ':' . $e->getLine(),
            'EXCEPTION',
            500,
            ['request_id' => $requestId, 'message' => $e->getMessage()]
        );

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WPForge] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }

        if (wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        // REST API context — send JSON
        if (defined('REST_REQUEST') && REST_REQUEST) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(500);
            echo wp_json_encode([
                'success'    => false,
                'request_id' => $requestId,
                'error'      => [
                    'code'    => 'INTERNAL_ERROR',
                    'message' => 'An internal error occurred.',
                ],
            ]);
            die;
        }
    }
}
