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
        $transient = get_transient($optionKey);

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
        $remoteAddr = '0.0.0.0';
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $candidate = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                $remoteAddr = $candidate;
            }
        }

        $trustedProxies = $this->config->getTrustedProxies();
        if (empty($trustedProxies) || !$this->isIpInList($remoteAddr, $trustedProxies)) {
            return $remoteAddr;
        }

        if (empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $remoteAddr;
        }

        $raw = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
        $parts = array_reverse(array_map('trim', explode(',', $raw)));

        foreach ($parts as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                return $remoteAddr;
            }
            if (!$this->isIpInList($ip, $trustedProxies)) {
                return $ip;
            }
        }

        return $remoteAddr;
    }

    /**
     * Check whether an IP matches any entry (exact IP or CIDR) in a list.
     *
     * @param string[] $list
     */
    private function isIpInList(string $ip, array $list): bool
    {
        foreach ($list as $entry) {
            if (!is_string($entry)) {
                continue;
            }
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            if ($this->ipMatches($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    private function ipMatches(string $ip, string $cidrOrIp): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (strpos($cidrOrIp, '/') === false) {
            if (!filter_var($cidrOrIp, FILTER_VALIDATE_IP)) {
                return false;
            }
            return inet_pton($ip) === inet_pton($cidrOrIp);
        }

        [$subnet, $prefixStr] = explode('/', $cidrOrIp, 2);
        if (!is_numeric($prefixStr) || !filter_var($subnet, FILTER_VALIDATE_IP)) {
            return false;
        }

        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $prefix = (int) $prefixStr;
        $maxBits = strlen($ipBin) * 8;
        if ($prefix < 0 || $prefix > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($prefix, 8);
        $remainderBits = $prefix % 8;

        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }

        if ($remainderBits > 0) {
            $mask = (0xFF << (8 - $remainderBits)) & 0xFF;
            if ((ord($ipBin[$fullBytes]) & $mask) !== (ord($subnetBin[$fullBytes]) & $mask)) {
                return false;
            }
        }

        return true;
    }
}
