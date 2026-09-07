<?php

namespace WPForge\API\Middleware;

use WPForge\Core\Config;

/**
 * Rate limiting middleware — throttles by IP.
 */
class RateLimit
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Middleware callable.
     */
    public function __invoke(\WP_REST_Request $request): bool|\WP_Error
    {
        if (!$this->config->rateLimitEnabled()) {
            return true;
        }

        $ip = $this->getClientIp();
        $window = $this->config->getRateLimitWindow();
        $maxRequests = $this->config->getRateLimitRequests();

        $optionKey = 'wpforge_ratelimit_' . md5($ip);
        $transient  = get_transient($optionKey);

        if ($transient === false) {
            set_transient($optionKey, 1, $window);
            return true;
        }

        $count = (int) $transient;

        if ($count >= $maxRequests) {
            return new \WP_Error(
                'wpforge_rate_limited',
                'Rate limit exceeded. Please try again later.',
                ['status' => 429]
            );
        }

        set_transient($optionKey, $count + 1, $window);
        return true;
    }

    private function getClientIp(): string
    {
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $raw = sanitize_text_field(wp_unslash($_SERVER[$header]));
                $ip  = trim(explode(',', $raw)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
