<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Data\AdminUserData;
use App\Domains\Identity\Models\User;

class UpdateUser
{
    public function handle(User $user, AdminUserData $data): User
    {
        $attrs = ['name' => $data->name, 'email' => $data->email];
        if ($data->password) {
            $attrs['password'] = $data->password;
        }
        $user->update($attrs);

        return $user;
    }
}
