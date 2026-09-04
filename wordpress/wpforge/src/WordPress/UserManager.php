<?php
namespace WPForge\WordPress;

/**
 * User management service — list, get, create, update users.
 */
class UserManager
{
    /**
     * List users with pagination.
     */
    public function getUsers(array $args = []): array
    {
        $defaults = [
            'number' => 20,
            'offset' => 0,
            'fields' => 'all',
        ];

        $args = wp_parse_args($args, $defaults);

        $query = new \WP_User_Query([
            'number' => $args['number'],
            'offset' => $args['offset'],
            'fields' => $args['fields'],
        ]);

        $users = [];
        foreach ($query->get_results() as $user) {
            $users[] = $this->formatUser($user);
        }

        return [
            'users' => $users,
            'total' => (int) $query->get_total(),
        ];
    }

    /**
     * Get a single user.
     */
    public function getUser(int $userId): ?array
    {
        $user = get_user_by('id', $userId);
        if (!$user) {
            return null;
        }
        return $this->formatUser($user);
    }

    /**
     * Create a new user.
     */
    public function createUser(array $data): array
    {
        $userId = wp_create_user(
            sanitize_user($data['username']),
            $data['password'] ?? wp_generate_password(),
            sanitize_email($data['email'] ?? '')
        );

        if (is_wp_error($userId)) {
            throw new \RuntimeException($userId->get_error_message());
        }

        // Set role if provided
        if (isset($data['role'])) {
            $user = get_user_by('id', $userId);
            $user->set_role(sanitize_text_field($data['role']));
        }

        // Set display name
        if (isset($data['display_name'])) {
            wp_update_user([
                'ID'           => $userId,
                'display_name' => sanitize_text_field($data['display_name']),
            ]);
        }

        return $this->getUser($userId);
    }

    /**
     * Update an existing user.
     */
    public function updateUser(int $userId, array $data): array
    {
        $updateData = ['ID' => $userId];

        if (isset($data['email'])) {
            $updateData['user_email'] = sanitize_email($data['email']);
        }
        if (isset($data['display_name'])) {
            $updateData['display_name'] = sanitize_text_field($data['display_name']);
        }
        if (isset($data['first_name'])) {
            $updateData['first_name'] = sanitize_text_field($data['first_name']);
        }
        if (isset($data['last_name'])) {
            $updateData['last_name'] = sanitize_text_field($data['last_name']);
        }
        if (isset($data['url'])) {
            $updateData['user_url'] = esc_url_raw($data['url']);
        }
        if (isset($data['description'])) {
            $updateData['description'] = sanitize_textarea_field($data['description']);
        }
        if (isset($data['password'])) {
            $updateData['user_pass'] = $data['password'];
        }

        $result = wp_update_user($updateData);
        if (is_wp_error($result)) {
            throw new \RuntimeException($result->get_error_message());
        }

        if (isset($data['role'])) {
            $user = get_user_by('id', $userId);
            if ($user) {
                $user->set_role(sanitize_text_field($data['role']));
            }
        }

        return $this->getUser($userId);
    }

    /**
     * Delete a user.
     */
    public function deleteUser(int $userId, bool $force = false): bool
    {
        $result = wp_delete_user($userId, $force);
        return $result !== null;
    }

    /* ------------------------------------------------------------------ */

    private function formatUser(\WP_User $user): array
    {
        return [
            'id'           => (int) $user->ID,
            'username'     => $user->user_login,
            'display_name' => $user->display_name,
            'email'        => $user->user_email,
            'url'          => $user->user_url,
            'registered'   => $user->user_registered,
            'roles'        => $user->roles,
            'capabilities' => $user->has_cap('manage_options'),
            'description'  => $user->description,
            'first_name'   => $user->first_name,
            'last_name'    => $user->last_name,
            'avatar_url'   => get_avatar_url($user->ID),
        ];
    }
}
