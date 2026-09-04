<?php

namespace WPForge\WordPress;

/**
 * Users service
 */
class UsersService
{
    public function getUsers(array $args = []): array
    {
        $query = new \WP_User_Query($args);
        $users = [];
        
        foreach ($query->get_results() as $user) {
            $users[] = $this->formatUser($user);
        }
        
        return [
            'users' => $users,
            'total' => $query->get_total(),
        ];
    }
    
    private function formatUser(\WP_User $user): array
    {
        return [
            'id' => $user->ID,
            'username' => $user->user_login,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'roles' => $user->roles,
        ];
    }
}
