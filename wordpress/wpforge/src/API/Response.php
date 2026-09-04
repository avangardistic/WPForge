<?php
namespace WPForge\API;

use WPForge\Core\RequestID;

/**
 * Standardised API response builder.
 */
class Response
{
    /**
     * Create a success response.
     */
    public static function success(mixed $data, int $status = 200): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success'    => true,
            'request_id' => RequestID::get(),
            'data'       => $data,
        ], $status);
    }

    /**
     * Create a paginated success response.
     */
    public static function paginated(array $items, int $total, int $page, int $perPage): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success'    => true,
            'request_id' => RequestID::get(),
            'data'       => $items,
            'pagination' => [
                'page'        => (int) $page,
                'per_page'    => (int) $perPage,
                'total'       => (int) $total,
                'total_pages' => (int) ceil($total / max($perPage, 1)),
            ],
        ], 200);
    }

    /**
     * Create an error response.
     */
    public static function error(string $code, string $message, int $status = 400): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success'    => false,
            'request_id' => RequestID::get(),
            'error'      => [
                'code'    => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
