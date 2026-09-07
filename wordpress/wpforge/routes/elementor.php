<?php

use WPForge\API\Response;
use WPForge\Elementor\Adapter;
use WPForge\Elementor\DocumentManager;
use WPForge\Elementor\TemplateManager;
use WPForge\Elementor\Validator;

$ns = WPFORGE_NAMESPACE;
$adapter = new Adapter();
$docManager = new DocumentManager();
$tplManager = new TemplateManager();

register_rest_route($ns, '/elementor/status', [
    'methods'             => 'GET',
    'callback'            => fn($request) => Response::success($adapter->getCapabilities()),
    'permission_callback' => \WPForge\API\Permissions::can('edit_pages'),
]);

register_rest_route($ns, '/elementor/documents', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($docManager) {
        if (!$docManager->isAvailable()) {
            return Response::error('ELEMENTOR_NOT_AVAILABLE', 'Elementor is not installed or active', 503);
        }
        $result = $docManager->listDocuments([
            'posts_per_page' => (int) ($request->get_param('per_page') ?: 50),
            'paged'          => (int) ($request->get_param('page') ?: 1),
            'post_type'      => $request->get_param('post_type') ?: 'any',
            'search'         => $request->get_param('search') ?: '',
        ]);
        return Response::paginated($result['documents'], $result['total'], $result['page'], $result['per_page']);
    },
    'permission_callback' => \WPForge\API\Permissions::can('edit_pages'),
]);

register_rest_route($ns, '/elementor/documents/(?P<id>\d+)', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($docManager) {
        $doc = $docManager->getDocument((int) $request['id']);
        return $doc ? Response::success($doc) : Response::error('NOT_FOUND', 'Document not found', 404);
    },
    'permission_callback' => \WPForge\API\Permissions::can('edit_pages'),
]);

register_rest_route($ns, '/elementor/documents/(?P<id>\d+)', [
    'methods'             => 'PUT, PATCH',
    'callback'            => function ($request) use ($docManager) {
        if (!current_user_can('edit_pages')) {
            return Response::error('FORBIDDEN', 'Permission denied', 403);
        }
        $validation = Validator::validateDocumentId((int) $request['id']);
        if ($validation instanceof \WP_Error) {
            return Response::error($validation->get_error_code(), $validation->get_error_message(), 400);
        }
        try {
            $result = $docManager->updateDocument((int) $request['id'], $request->get_json_params());
            return Response::success($result);
        } catch (\Exception $e) {
            return Response::error('UPDATE_FAILED', $e->getMessage(), 500);
        }
    },
    'permission_callback' => \WPForge\API\Permissions::can('edit_pages'),
]);

register_rest_route($ns, '/elementor/templates', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($tplManager) {
        $result = $tplManager->listTemplates([
            'posts_per_page' => (int) ($request->get_param('per_page') ?: 50),
            'paged'          => (int) ($request->get_param('page') ?: 1),
            'type'           => $request->get_param('type') ?: '',
        ]);
        return Response::paginated($result['templates'], $result['total'], $result['page'], $result['per_page']);
    },
    'permission_callback' => \WPForge\API\Permissions::can('edit_pages'),
]);

register_rest_route($ns, '/elementor/templates/(?P<id>\d+)', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($tplManager) {
        $tpl = $tplManager->getTemplate((int) $request['id']);
        return $tpl ? Response::success($tpl) : Response::error('NOT_FOUND', 'Template not found', 404);
    },
    'permission_callback' => \WPForge\API\Permissions::can('edit_pages'),
]);
