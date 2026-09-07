<?php

namespace WPForge\API;

/**
 * Base controller with shared helpers for route handlers.
 */
class Controller
{
    protected string $namespace = WPFORGE_NAMESPACE;

    /**
     * Generate a unique request ID.
     */
    protected function requestId(): string
    {
        return RequestID::get();
    }

    /**
     * Wrap data in a standard success response.
     */
    protected function success(mixed $data, int $status = 200): \WP_REST_Response
    {
        return Response::success($data, $status);
    }

    /**
     * Wrap data in a paginated response.
     */
    protected function paginated(array $items, int $total, int $page, int $perPage): \WP_REST_Response
    {
        return Response::paginated($items, $total, $page, $perPage);
    }

    /**
     * Create an error response.
     */
    protected function error(string $code, string $message, int $status = 400): \WP_REST_Response
    {
        return Response::error($code, $message, $status);
    }

    /**
     * Require authentication; return WP_Error on failure.
     */
    protected function requireAuth(): bool|\WP_Error
    {
        if (!is_user_logged_in()) {
            return new \WP_Error('wpforge_unauthorized', 'Authentication required.', ['status' => 401]);
        }
        return true;
    }

    /**
     * Require a specific capability; return WP_Error on failure.
     */
    protected function requireCapability(string $cap): bool|\WP_Error
    {
        $auth = $this->requireAuth();
        if ($auth instanceof \WP_Error) {
            return $auth;
        }
        if (!current_user_can($cap)) {
            return new \WP_Error('wpforge_forbidden', sprintf('Missing capability: %s', $cap), ['status' => 403]);
        }
        return true;
    }

    /**
     * Convenience: return an error \WP_REST_Response from a \WP_Error.
     */
    protected function wpError(\WP_Error $wpError): \WP_REST_Response
    {
        $status = 400;
        $data = $wpError->get_error_data();
        if (isset($data['status'])) {
            $status = (int) $data['status'];
        }
        return Response::error($wpError->get_error_code(), $wpError->get_error_message(), $status);
    }
}
