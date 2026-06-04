<?php
namespace App\Domains\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class SuperAdminRoleSeeder extends Seeder
{
    public function run(): void { Role::findOrCreate('super-admin'); }
}
