<?php
use WPForge\API\Response;
use WPForge\Auth\TokenManager;

$ns = WPFORGE_NAMESPACE;
$tokenManager = new TokenManager();

register_rest_route($ns, '/tokens', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($tokenManager) {
        if (!is_user_logged_in()) {
            return Response::error('UNAUTHORIZED', 'Authentication required.', 401);
        }
        $user = wp_get_current_user();
        $tokens = $tokenManager->listTokens((int) $user->ID);
        return Response::success(['tokens' => $tokens, 'count' => count($tokens)]);
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/tokens', [
    'methods'             => 'POST',
    'callback'            => function ($request) use ($tokenManager) {
        if (!is_user_logged_in()) {
            return Response::error('UNAUTHORIZED', 'Authentication required.', 401);
        }
        if (!current_user_can('edit_users')) {
            return Response::error('FORBIDDEN', 'Permission denied', 403);
        }
        
        $data = $request->get_json_params();
        $userId = isset($data['user_id']) ? (int) $data['user_id'] : (int) get_current_user_id();
        $description = $data['description'] ?? 'API Token';
        
        // Ensure user can create tokens for themselves or others
        if ($userId !== get_current_user_id() && !current_user_can('edit_users')) {
            return Response::error('FORBIDDEN', 'Cannot create tokens for other users', 403);
        }
        
        try {
            $token = $tokenManager->createToken($userId, $description);
            return Response::success($token, 201);
        } catch (\Exception $e) {
            return Response::error('TOKEN_CREATION_FAILED', $e->getMessage(), 500);
        }
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/tokens/(?P<id>\w+)', [
    'methods'             => 'DELETE',
    'callback'            => function ($request) use ($tokenManager) {
        if (!is_user_logged_in()) {
            return Response::error('UNAUTHORIZED', 'Authentication required.', 401);
        }
        if (!current_user_can('edit_users')) {
            return Response::error('FORBIDDEN', 'Permission denied', 403);
        }
        
        $tokenId = $request['id'];
        
        // Users can only revoke their own tokens unless they have edit_users capability
        $token = $tokenManager->getTokenById($tokenId);
        if (!$token) {
            return Response::error('NOT_FOUND', 'Token not found', 404);
        }
        
        if ($token->user_id !== get_current_user_id() && !current_user_can('edit_users')) {
            return Response::error('FORBIDDEN', 'Cannot revoke tokens for other users', 403);
        }
        
        $revoked = $tokenManager->revokeToken($tokenId);
        
        if (!$revoked) {
            return Response::error('REVOKE_FAILED', 'Failed to revoke token', 500);
        }
        
        return Response::success(['success' => true, 'revoked' => true, 'token_id' => $tokenId]);
    },
    'permission_callback' => 'is_user_logged_in',
]);