<?php
use WPForge\API\Response;
use WPForge\Database\Inspector;
use WPForge\Core\Config;

$ns = WPFORGE_NAMESPACE;
$dbInspector = new Inspector();
$config = new Config();

register_rest_route($ns, '/database/status', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($dbInspector, $config) {
        $status = $dbInspector->getStatus();
        
        // SECURITY: Redact sensitive database credentials if configured
        if ($config->shouldRedactDbCredentials()) {
            unset($status['database'], $status['db_user'], $status['db_host']);
        }
        
        return Response::success($status);
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/database/tables', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($dbInspector) {
        $tables = $dbInspector->listTables();
        return Response::success(['tables' => $tables, 'count' => count($tables)]);
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/database/tables/(?P<name>[a-zA-Z0-9_]+)', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($dbInspector) {
        try {
            $columns = $dbInspector->describeTable($request['name']);
            return Response::success(['table' => $request['name'], 'columns' => $columns]);
        } catch (\Exception $e) {
            return Response::error('DESCRIBE_FAILED', $e->getMessage(), 400);
        }
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/database/query', [
    'methods'             => 'POST',
    'callback'            => function ($request) use ($dbInspector) {
        $data = $request->get_json_params();
        if (empty($data['sql'])) {
            return Response::error('MISSING_QUERY', 'SQL query is required', 400);
        }
        $config = new Config();
        $trimmed = ltrim($data['sql']);
        if (stripos($trimmed, 'SELECT') !== 0) {
            if (!$config->allowDatabaseWrites()) {
                return Response::error('WRITES_DISABLED', 'Write queries are disabled', 403);
            }
        }
        try {
            return Response::success($dbInspector->query($data['sql'], $data['params'] ?? []));
        } catch (\Exception $e) {
            return Response::error('QUERY_FAILED', $e->getMessage(), 400);
        }
    },
    'permission_callback' => 'is_user_logged_in',
]);