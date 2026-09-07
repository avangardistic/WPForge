<?php
namespace WPForge\API\Middleware;

use WPForge\Logging\Logger;

/**
 * Request logging middleware — records every incoming request.
 */
class Logging
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Middleware callable.
     */
    public function __invoke(\WP_REST_Request $request): bool
    {
        // Record the request for post-response audit if needed.
        // Actual response-time logging is handled by ErrorHandler.
        $this->logger->logSuccess(
            'request_received',
            $request->get_route(),
            200,
            [
                'method'     => $request->get_method(),
                'params'     => array_keys($request->get_params()),
                'request_id' => $request->get_header('X-Request-Id') ?? '',
            ]
        );

        return true;
    }
}
