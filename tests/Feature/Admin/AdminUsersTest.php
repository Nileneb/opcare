<?php

use App\Domains\Identity\Database\Seeders\RolesSeeder;
use App\Domains\Identity\Models\{Tenant, User};
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Admin\Users;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->t);
    $this->admin = User::factory()->create(['tenant_id' => $this->t->id]);
    $this->admin->assignRole('admin');
});

it('legt Mitarbeitende mit Rolle an', function () {
    Livewire::actingAs($this->admin)->test(Users::class)
        ->set('name', 'Hans Helfer')->set('email', 'hans@opcare.local')
        ->set('password', 'geheim-1234')->set('role', 'pflegehilfskraft')
        ->call('save')->assertHasNoErrors();

    $u = User::where('email', 'hans@opcare.local')->first();
    expect($u->tenant_id)->toBe($this->t->id)->and($u->hasRole('pflegehilfskraft'))->toBeTrue();
});
