<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterUser
{
    /**
     * Legt einen Benutzer an, ordnet ihn einem Mandanten zu und vergibt eine Rolle.
     * Ohne $tenantId wird der erste Mandant genutzt (single-home v1) bzw. einer angelegt.
     */
    public function handle(string $name, string $email, string $password, ?int $tenantId = null, string $role = 'pflegefachkraft'): User
    {
        return DB::transaction(function () use ($name, $email, $password, $tenantId, $role) {
            $tenant = $tenantId
                ? Tenant::findOrFail($tenantId)
                : (Tenant::query()->first() ?? Tenant::create([
                    'name' => 'Mein Pflegeheim',
                    'slug' => 'heim-'.Str::lower(Str::random(6)),
                ]));

            app(CurrentTenant::class)->set($tenant);

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password, // wird per 'hashed'-Cast gehasht
                'tenant_id' => $tenant->id,
            ]);

            $user->assignRole($role);

            return $user;
        });
    }
}
