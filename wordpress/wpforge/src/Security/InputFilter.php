<?php
namespace WPForge\Security;

/**
 * Request input filter — sanitise and validate incoming request data.
 */
class InputFilter
{
    /**
     * Sanitize all parameters from a WP_REST_Request.
     *
     * @return array<string, mixed>
     */
    public static function sanitizeRequestParams(\WP_REST_Request $request, array $allowedParams = []): array
    {
        $params = $request->get_params();
        $sanitized = [];

        foreach ($params as $key => $value) {
            if (!empty($allowedParams) && !in_array($key, $allowedParams, true)) {
                continue;
            }

            $sanitized[$key] = self::sanitizeValue($key, $value);
        }

        return $sanitized;
    }

    /**
     * Sanitize a single value based on common key patterns.
     */
    public static function sanitizeValue(string $key, mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn($v) => is_string($v) ? self::sanitizeValue($key, $v) : $v, $value);
        }

        if (!is_string($value)) {
            return $value;
        }

        // Path-like keys
        if (in_array($key, ['path', 'file_path', 'directory', 'file'], true)) {
            return Sanitizer::path($value);
        }

        // Email-like keys
        if (in_array($key, ['email', 'user_email'], true)) {
            return Sanitizer::email($value);
        }

        // URL-like keys
        if (str_ends_with($key, '_url') || str_ends_with($key, '_uri') || $key === 'url') {
            return Sanitizer::url($value);
        }

        // Content keys — allow HTML
        if (in_array($key, ['content', 'post_content', 'description', 'body'], true)) {
            return Sanitizer::html($value);
        }

        // Title-like keys
        if (str_ends_with($key, '_title') || $key === 'title' || str_ends_with($key, '_name')) {
            return Sanitizer::text($value);
        }

        // Default: plain text
        return Sanitizer::text($value);
    }

    /**
     * Strip sensitive fields from a data array before logging.
     */
    public static function stripSensitive(array $data): array
    {
        $sensitive = [
            'password', 'secret', 'token', 'api_key', 'apikey',
            'authorization', 'cookie', 'credential', 'private_key',
            'app_password', 'application_password',
        ];

        foreach ($data as $key => $value) {
            $lower = strtolower($key);
            foreach ($sensitive as $pattern) {
                if (strpos($lower, $pattern) !== false) {
                    $data[$key] = '[REDACTED]';
                    break;
                }
            }
        }

        return $data;
    }
}
