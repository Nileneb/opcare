<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Enums\WunschTyp;
use App\Domains\Scheduling\Models\Dienstwunsch;
use App\Livewire\Scheduling\Dienstplan;
use App\Livewire\Scheduling\Wunschdienstplan;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    foreach (['admin', 'pflegehilfskraft'] as $r) {
        Role::findOrCreate($r);
    }
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Sandra Vogt']);
    $this->user->assignRole('pflegehilfskraft');
    $this->montag = Carbon::parse('today')->startOfWeek()->toDateString();
});

it('speichert und löscht eigene Dienstwünsche', function () {
    $this->actingAs($this->user);

    Livewire::test(Wunschdienstplan::class)
        ->set("w.{$this->montag}.typ", WunschTyp::Frei->value)
        ->set("w.{$this->montag}.notiz", 'Arzttermin')
        ->call('speichern');
    expect(Dienstwunsch::where('user_id', $this->user->id)->count())->toBe(1);

    Livewire::test(Wunschdienstplan::class)->set("w.{$this->montag}.typ", '')->call('speichern');
    expect(Dienstwunsch::where('user_id', $this->user->id)->count())->toBe(0);
});

it('zeigt der PDL die Dienstwünsche im Dienstplan-Grid', function () {
    Dienstwunsch::create(['user_id' => $this->user->id, 'datum' => $this->montag, 'typ' => WunschTyp::Frei, 'notiz' => 'Arzttermin']);
    $leitung = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $leitung->assignRole('admin');
    $this->actingAs($leitung);

    Livewire::test(Dienstplan::class)
        ->assertViewHas('wuensche', fn ($w) => isset($w[$this->user->id][$this->montag]))
        ->assertSee('frei');
});
