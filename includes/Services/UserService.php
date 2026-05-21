<?php

declare(strict_types=1);

namespace WpEditorialAbilities\Services;

use WP_User;

final class UserService
{
    public function listAuthors(array $input): array
    {
        $users = get_users([
            'capability' => 'edit_posts',
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);

        return array_map([$this, 'userResponse'], $users);
    }

    private function userResponse(WP_User $user): array
    {
        return [
            'id' => (int) $user->ID,
            'display_name' => $user->display_name,
            'user_login' => $user->user_login,
            'roles' => array_values($user->roles),
        ];
    }
}
