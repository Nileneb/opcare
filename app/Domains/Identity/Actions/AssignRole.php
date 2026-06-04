<?php
namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;

class AssignRole
{
    public function handle(User $user, string $role): User
    {
        $user->syncRoles([$role]); // genau eine Rolle pro Nutzer in v1
        return $user;
    }
}
