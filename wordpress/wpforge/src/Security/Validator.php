<?php

namespace WPForge\Security;

/**
 * Security validation service for WPForge
 * 
 * Handles input validation, path traversal prevention, and security checks.
 */
class Validator
{
    /**
     * Validate and sanitize a file path to prevent path traversal
     * 
     * @param string $path The path to validate
     * @param string|null $root_dir Optional root directory (defaults to WordPress root)
     * @return string|false Validated absolute path or false if invalid
     */
    public function validatePath(string $path, ?string $root_dir = null): string|false
    {
        if (empty($path)) {
            return false;
        }

        // Use WordPress root as default
        if (null === $root_dir) {
            $root_dir = ABSPATH;
        }

        $root_dir = rtrim($root_dir, '/') . '/';

        // Reject paths with null bytes
        if (strpos($path, "\0") !== false) {
            return false;
        }

        // Normalize the path
        // First, handle Windows-style backslashes
        $path = str_replace('\\', '/', $path);

        // Remove any leading slashes to make it relative
        $path = ltrim($path, '/');

        // Build the full path
        $full_path = realpath($root_dir . $path);

        // If the path doesn't exist yet (for new files), validate the parent directory
        if ($full_path === false) {
            // For new files, check that the parent directory exists and is valid
            $parent_dir = dirname($root_dir . $path);
            $parent_real = realpath($parent_dir);
            
            if ($parent_real === false) {
                return false;
            }
            
            // Check that parent is within root
            if (strpos($parent_real, realpath($root_dir)) !== 0) {
                return false;
            }
            
            // Return the normalized path for the new file
            return $parent_real . '/' . basename($path);
        }

        // Ensure the resolved path is within the root directory
        $real_root = realpath($root_dir);
        if (strpos($full_path, $real_root) !== 0) {
            return false;
        }

        return $full_path;
    }

    /**
     * Check if a path contains traversal attempts
     * 
     * @param string $path Path to check
     * @return bool True if traversal detected
     */
    public function containsTraversal(string $path): bool
    {
        // Check for common traversal patterns
        $patterns = [
            '\.\.',           // Basic parent directory
            '\.\.\\',         // Windows style
            '\.\./',          // Unix style
            '%2e%2e',         // URL encoded
            '%252e%252e',     // Double URL encoded
            '....//',         // Bypass attempt
            '..;/ ',          // Bypass attempt
        ];

        $decoded_path = urldecode($path);
        
        foreach ($patterns as $pattern) {
            if (stripos($decoded_path, str_replace(['\\.', '\\/'], ['.', '/'], $pattern)) !== false) {
                return true;
            }
        }

        // Also check if normalized path escapes root
        $normalized = $this->normalizePath($path);
        if (strpos($normalized, '..') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Normalize a file path
     * 
     * @param string $path Path to normalize
     * @return string Normalized path
     */
    public function normalizePath(string $path): string
    {
        // Convert backslashes to forward slashes
        $path = str_replace('\\', '/', $path);
        
        // Remove duplicate slashes
        $path = preg_replace('#/+#', '/', $path);
        
        // Resolve .. and . components
        $parts = explode('/', $path);
        $result = [];
        
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            
            if ($part === '..') {
                array_pop($result);
            } else {
                $result[] = $part;
            }
        }
        
        return implode('/', $result);
    }

    /**
     * Validate a filename for safety
     * 
     * @param string $filename Filename to validate
     * @return string|false Sanitized filename or false if invalid
     */
    public function validateFilename(string $filename): string|false
    {
        if (empty($filename)) {
            return false;
        }

        // Remove path separators
        $filename = basename($filename);

        // Remove null bytes
        $filename = str_replace("\0", '', $filename);

        // Sanitize the filename using WordPress function
        $filename = sanitize_file_name($filename);

        if (empty($filename)) {
            return false;
        }

        return $filename;
    }

    /**
     * Validate MIME type against allowed list
     * 
     * @param string $mime_type MIME type to validate
     * @param array|null $allowed_list Allowed MIME types (null for WordPress defaults)
     * @return bool
     */
    public function isValidMimeType(string $mime_type, ?array $allowed_list = null): bool
    {
        if (null === $allowed_list) {
            // Get WordPress allowed mime types
            $allowed_list = get_allowed_mime_types();
        }

        return in_array($mime_type, $allowed_list, true);
    }

    /**
     * Check if a file extension is dangerous
     * 
     * @param string $extension File extension
     * @return bool True if extension is potentially dangerous
     */
    public function isDangerousExtension(string $extension): bool
    {
        $dangerous_extensions = [
            'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
            'exe', 'bat', 'cmd', 'sh', 'bash',
            'pl', 'py', 'rb',
            'htaccess', 'htpasswd',
            'ini', 'conf',
            'asp', 'aspx', 'jsp', 'cgi',
        ];

        return in_array(strtolower(ltrim($extension, '.')), $dangerous_extensions, true);
    }

    /**
     * Sanitize input data recursively
     * 
     * @param mixed $data Data to sanitize
     * @return mixed Sanitized data
     */
    public function sanitizeInput(mixed $data): mixed
    {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }

        if (is_string($data)) {
            // Remove null bytes
            $data = str_replace("\0", '', $data);
            
            // Sanitize based on context
            if ($this->looksLikeHTML($data)) {
                return wp_kses_post($data);
            }
            
            return sanitize_text_field($data);
        }

        return $data;
    }

    /**
     * Check if a string appears to contain HTML
     * 
     * @param string $string String to check
     * @return bool
     */
    private function looksLikeHTML(string $string): bool
    {
        return preg_match('/<[a-z][\s\S]*>/i', $string) > 0;
    }

    /**
     * Generate a CSRF token
     * 
     * @param string $action Action name for the token
     * @return string
     */
    public function generateCSRFToken(string $action): string
    {
        return wp_hash($action . wp_salt(), 'nonce');
    }

    /**
     * Verify a CSRF token
     * 
     * @param string $token Token to verify
     * @param string $action Action name
     * @return bool
     */
    public function verifyCSRFToken(string $token, string $action): bool
    {
        return hash_equals($this->generateCSRFToken($action), $token);
    }

    /**
     * Rate limit check
     * 
     * @param string $identifier Unique identifier for rate limiting (e.g., user ID, IP)
     * @param int $max_requests Maximum requests allowed
     * @param int $window_seconds Time window in seconds
     * @return bool True if request is allowed
     */
    public function checkRateLimit(string $identifier, int $max_requests = 100, int $window_seconds = 60): bool
    {
        $transient_key = 'wpforge_rate_limit_' . md5($identifier);
        $current = get_transient($transient_key);

        if ($current === false) {
            // First request in window
            set_transient($transient_key, 1, $window_seconds);
            return true;
        }

        if ($current >= $max_requests) {
            return false;
        }

        set_transient($transient_key, $current + 1, $window_seconds);
        return true;
    }

    /**
     * Get remaining rate limit requests
     * 
     * @param string $identifier Unique identifier
     * @param int $max_requests Maximum requests allowed
     * @return int Remaining requests
     */
    public function getRateLimitRemaining(string $identifier, int $max_requests = 100): int
    {
        $transient_key = 'wpforge_rate_limit_' . md5($identifier);
        $current = get_transient($transient_key);

        if ($current === false) {
            return $max_requests;
        }

        return max(0, $max_requests - $current);
    }

    /**
     * Validate SQL query is read-only (SELECT only)
     * 
     * @param string $query SQL query to validate
     * @return bool True if query is read-only
     */
    public function isReadOnlyQuery(string $query): bool
    {
        $query = trim($query);
        
        // Only allow SELECT, SHOW, DESCRIBE, EXPLAIN
        $allowed_prefixes = ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'];
        
        foreach ($allowed_prefixes as $prefix) {
            if (stripos($query, $prefix) === 0) {
                // Additional check: ensure no semicolon followed by write operation
                if (preg_match('/;\s*(INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|TRUNCATE)/i', $query)) {
                    return false;
                }
                return true;
            }
        }
        
        return false;
    }
}
