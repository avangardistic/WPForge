<?php
namespace WPForge\API;

/**
 * REST API Router — wraps WordPress register_rest_route with middleware support.
 */
class Router
{
    private const NAMESPACE = 'wpforge/v1';

    /** @var array<string, array{method: string, endpoint: string, callback: callable, args: array}> */
    private array $routes = [];

    /** @var callable[] */
    private array $middleware = [];

    /**
     * Register a route definition (deferred until registerRestRoutes).
     */
    public function register(string $method, string $endpoint, callable $callback, array $args = []): void
    {
        $this->routes[] = [
            'method'   => strtoupper($method),
            'endpoint' => $endpoint,
            'callback' => $callback,
            'args'     => $args,
        ];
    }

    /**
     * Register a middleware callable executed before every route callback.
     */
    public function addMiddleware(callable $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Commit all deferred routes to the WordPress REST API.
     */
    public function registerRestRoutes(): void
    {
        foreach ($this->routes as $route) {
            $callback = $this->wrapCallback($route['callback']);

            register_rest_route(
                self::NAMESPACE,
                $route['endpoint'],
                [
                    'methods'             => $route['method'],
                    'callback'            => $callback,
                    'permission_callback' => [$this, 'permissionCallback'],
                    'args'                => $route['args'],
                ]
            );
        }
    }

    /**
     * Default permission callback — requires authenticated user.
     */
    public function permissionCallback(\WP_REST_Request $request): bool|\WP_Error
    {
        if (!is_user_logged_in()) {
            return new \WP_Error(
                'wpforge_unauthorized',
                'Authentication required.',
                ['status' => 401]
            );
        }
        return true;
    }

    /**
     * Wrap a callback with the registered middleware chain.
     */
    private function wrapCallback(callable $callback): callable
    {
        return function (\WP_REST_Request $request) use ($callback) {
            foreach ($this->middleware as $mw) {
                $result = $mw($request);
                if ($result instanceof \WP_REST_Response) {
                    return $result;
                }
                if ($result instanceof \WP_Error) {
                    return new \WP_REST_Response([
                        'success' => false,
                        'error'   => [
                            'code'    => $result->get_error_code(),
                            'message' => $result->get_error_message(),
                        ],
                    ], (int) ($result->get_error_data()['status'] ?? 400));
                }
            }

            return $callback($request);
        };
    }

    /**
     * Get all registered routes (useful for tests / manifest).
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
