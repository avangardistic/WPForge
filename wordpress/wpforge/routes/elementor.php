<?php

namespace WPForge\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPForge\Elementor\ElementorService;
use WPForge\Core\Config;

/**
 * Elementor routes for WPForge API
 */
class ElementorRoutes extends BaseRoutes
{
    private ElementorService $elementor;
    private Config $config;

    public function __construct()
    {
        parent::__construct();
        $this->elementor = new ElementorService();
        $this->config = new Config();
    }

    public static function register(): void
    {
        $instance = new self();

        // GET /elementor/status - Check Elementor status
        register_rest_route($instance->namespace, '/elementor/status', [
            'methods' => 'GET',
            'callback' => [$instance, 'getStatus'],
            'permission_callback' => '__return_true',
        ]);

        // GET /elementor/documents - List Elementor documents
        register_rest_route($instance->namespace, '/elementor/documents', [
            'methods' => 'GET',
            'callback' => [$instance, 'getDocuments'],
            'permission_callback' => [$instance, 'checkAuth'],
        ]);

        // GET /elementor/document/{id} - Get specific document
        register_rest_route($instance->namespace, '/elementor/document/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$instance, 'getDocument'],
            'permission_callback' => [$instance, 'checkAuth'],
        ]);

        // PUT/PATCH /elementor/document/{id} - Update document
        register_rest_route($instance->namespace, '/elementor/document/(?P<id>\d+)', [
            'methods' => ['PUT', 'PATCH'],
            'callback' => [$instance, 'updateDocument'],
            'permission_callback' => [$instance, 'checkEditPermission'],
        ]);

        // POST /elementor/page - Create Elementor page
        register_rest_route($instance->namespace, '/elementor/page', [
            'methods' => 'POST',
            'callback' => [$instance, 'createPage'],
            'permission_callback' => [$instance, 'checkEditPermission'],
        ]);

        // GET /elementor/templates - List library templates
        register_rest_route($instance->namespace, '/elementor/templates', [
            'methods' => 'GET',
            'callback' => [$instance, 'getTemplates'],
            'permission_callback' => [$instance, 'checkAuth'],
        ]);

        // GET /elementor/widgets - List available widgets
        register_rest_route($instance->namespace, '/elementor/widgets', [
            'methods' => 'GET',
            'callback' => [$instance, 'getWidgets'],
            'permission_callback' => [$instance, 'checkAuth'],
        ]);
    }

    public function getStatus(): WP_REST_Response
    {
        return $this->successResponse($this->elementor->getStatus());
    }

    public function getDocuments(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->elementor->isActive()) {
            return $this->errorResponse('elementor_not_active', 'Elementor is not active.', 400);
        }

        $args = [
            'posts_per_page' => $request->get_param('per_page') ?? 50,
            'paged' => $request->get_param('page') ?? 1,
            'post_type' => $request->get_param('post_type') ?? 'any',
        ];

        $result = $this->elementor->getDocuments($args);
        return $this->successResponse($result);
    }

    public function getDocument(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->elementor->isActive()) {
            return $this->errorResponse('elementor_not_active', 'Elementor is not active.', 400);
        }

        $post_id = (int) $request->get_param('id');
        $document = $this->elementor->getDocument($post_id);

        if (!$document) {
            return $this->errorResponse('elementor_document_not_found', 'Elementor document not found.', 404);
        }

        return $this->successResponse($document);
    }

    public function updateDocument(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->elementor->isActive()) {
            return $this->errorResponse('elementor_not_active', 'Elementor is not active.', 400);
        }

        $post_id = (int) $request->get_param('id');
        $elementor_data = $request->get_param('elementor_data');
        $settings = $request->get_param('settings') ?? [];
        $dry_run = $request->get_param('dry_run') ?? false;

        if (empty($elementor_data)) {
            return $this->errorResponse('missing_elementor_data', 'Elementor data is required.', 400);
        }

        // Dry run mode
        if ($dry_run) {
            $current = $this->elementor->getDocument($post_id);
            return $this->successResponse([
                'dry_run' => true,
                'would_update' => $post_id,
                'current_data_size' => strlen($current['elementor_data_raw'] ?? ''),
                'new_data_size' => strlen(wp_json_encode($elementor_data)),
                'changes_preview' => [
                    'sections_count' => count($elementor_data),
                ],
            ]);
        }

        $result = $this->elementor->updateDocument($post_id, $elementor_data, $settings);

        if (is_wp_error($result)) {
            $this->logMutation('elementor_update', "post_{$post_id}", false, 400, $result->get_error_code());
            return $this->errorResponse($result->get_error_code(), $result->get_error_message(), 400);
        }

        $this->logMutation('elementor_update', "post_{$post_id}", true, 200);
        return $this->successResponse($result);
    }

    public function createPage(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->elementor->isActive()) {
            return $this->errorResponse('elementor_not_active', 'Elementor is not active.', 400);
        }

        $args = [
            'post_title' => $request->get_param('title') ?? 'Untitled',
            'post_content' => $request->get_param('content') ?? '',
            'post_status' => $request->get_param('status') ?? 'draft',
            'post_type' => $request->get_param('type') ?? 'page',
            'elementor_data' => $request->get_param('elementor_data') ?? [],
        ];

        $result = $this->elementor->createPage($args);

        if (is_wp_error($result)) {
            $this->logMutation('elementor_create', 'page', false, 400, $result->get_error_code());
            return $this->errorResponse($result->get_error_code(), $result->get_error_message(), 400);
        }

        $this->logMutation('elementor_create', "post_{$result['post_id']}", true, 201);
        return $this->successResponse($result, 201);
    }

    public function getTemplates(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->elementor->isActive()) {
            return $this->errorResponse('elementor_not_active', 'Elementor is not active.', 400);
        }

        $type = $request->get_param('type');
        $templates = $this->elementor->getLibraryTemplates($type);

        return $this->successResponse(['templates' => $templates]);
    }

    public function getWidgets(): WP_REST_Response
    {
        if (!$this->elementor->isActive()) {
            return $this->errorResponse('elementor_not_active', 'Elementor is not active.', 400);
        }

        $widgets = $this->elementor->getAvailableWidgets();
        return $this->successResponse(['widgets' => $widgets]);
    }

    public function checkEditPermission(): bool|WP_Error
    {
        $auth = $this->checkAuth();
        if (is_wp_error($auth)) {
            return $auth;
        }

        return $this->checkCapability('edit_pages');
    }
}
