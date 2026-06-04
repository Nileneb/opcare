<?php
namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Data\AdminUserData;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Support\Facades\DB;

class CreateUser
{
    public function handle(AdminUserData $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password, // 'hashed'-Cast
                'tenant_id' => app(CurrentTenant::class)->id(),
            ]);
            $user->assignRole($data->role);
            return $user;
        });
    }
}
