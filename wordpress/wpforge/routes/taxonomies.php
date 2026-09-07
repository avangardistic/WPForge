<?php

use WPForge\API\Response;
use WPForge\WordPress\TaxonomyManager;

$ns = WPFORGE_NAMESPACE;
$tm = new TaxonomyManager();

register_rest_route($ns, '/taxonomies/(?P<taxonomy>[a-z_]+)/terms', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($tm) {
        $result = $tm->getTerms($request['taxonomy'], [
            'per_page' => (int) ($request->get_param('per_page') ?: 100),
            'offset'   => (int) ($request->get_param('offset') ?: 0),
        ]);
        return Response::success($result);
    },
    'permission_callback' => \WPForge\API\Permissions::can('manage_categories'),
]);

register_rest_route($ns, '/taxonomies/(?P<taxonomy>[a-z_]+)/terms/(?P<id>\d+)', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($tm) {
        $term = $tm->getTerm($request['taxonomy'], (int) $request['id']);
        return $term ? Response::success($term) : Response::error('NOT_FOUND', 'Term not found', 404);
    },
    'permission_callback' => \WPForge\API\Permissions::can('manage_categories'),
]);

register_rest_route($ns, '/taxonomies/(?P<taxonomy>[a-z_]+)/terms', [
    'methods'             => 'POST',
    'callback'            => function ($request) use ($tm) {
        if (!current_user_can('manage_categories')) {
            return Response::error('FORBIDDEN', 'Permission denied', 403);
        }
        try {
            $term = $tm->createTerm($request['taxonomy'], $request->get_json_params());
            return Response::success($term, 201);
        } catch (\Exception $e) {
            return Response::error('CREATE_FAILED', $e->getMessage(), 500);
        }
    },
    'permission_callback' => \WPForge\API\Permissions::can('manage_categories'),
]);

register_rest_route($ns, '/taxonomies/(?P<taxonomy>[a-z_]+)/terms/(?P<id>\d+)', [
    'methods'             => 'PUT, PATCH',
    'callback'            => function ($request) use ($tm) {
        if (!current_user_can('manage_categories')) {
            return Response::error('FORBIDDEN', 'Permission denied', 403);
        }
        try {
            $term = $tm->updateTerm($request['taxonomy'], (int) $request['id'], $request->get_json_params());
            return Response::success($term);
        } catch (\Exception $e) {
            return Response::error('UPDATE_FAILED', $e->getMessage(), 500);
        }
    },
    'permission_callback' => \WPForge\API\Permissions::can('manage_categories'),
]);

register_rest_route($ns, '/taxonomies/(?P<taxonomy>[a-z_]+)/terms/(?P<id>\d+)', [
    'methods'             => 'DELETE',
    'callback'            => function ($request) use ($tm) {
        if (!current_user_can('manage_categories')) {
            return Response::error('FORBIDDEN', 'Permission denied', 403);
        }
        $result = $tm->deleteTerm($request['taxonomy'], (int) $request['id']);
        return $result
            ? Response::success(['deleted' => true, 'id' => (int) $request['id']])
            : Response::error('DELETE_FAILED', 'Failed to delete term', 500);
    },
    'permission_callback' => \WPForge\API\Permissions::can('manage_categories'),
]);
