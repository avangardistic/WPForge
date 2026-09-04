<?php
use WPForge\API\Response;
use WPForge\WordPress\ThemeManager;

$ns = WPFORGE_NAMESPACE;
$tm = new ThemeManager();

register_rest_route($ns, '/themes', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($tm) {
        return Response::success($tm->getThemes());
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/themes/(?P<stylesheet>[a-zA-Z0-9_-]+)', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($tm) {
        $theme = $tm->getTheme($request['stylesheet']);
        return $theme ? Response::success($theme) : Response::error('NOT_FOUND', 'Theme not found', 404);
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/themes/activate', [
    'methods'             => 'POST',
    'callback'            => function ($request) use ($tm) {
        if (!current_user_can('switch_themes')) {
            return Response::error('FORBIDDEN', 'You do not have permission to switch themes', 403);
        }
        $stylesheet = $request->get_param('theme');
        if (!$stylesheet) {
            return Response::error('MISSING_THEME', 'Theme stylesheet is required', 400);
        }
        try {
            $result = $tm->activateTheme($stylesheet);
            return Response::success(['activated' => $result, 'theme' => $tm->getActiveTheme()]);
        } catch (\Exception $e) {
            return Response::error('ACTIVATE_FAILED', $e->getMessage(), 500);
        }
    },
    'permission_callback' => 'is_user_logged_in',
]);