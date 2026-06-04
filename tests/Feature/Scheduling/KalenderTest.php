<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Scheduling\Enums\CalendarEventType;
use App\Domains\Scheduling\Models\CalendarEvent;
use App\Domains\Scheduling\Models\RecurrenceRule;
use App\Livewire\Scheduling\Kalender;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('pflegefachkraft');
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('pflegefachkraft');
    $this->actingAs($this->user);
});

afterEach(fn () => Carbon::setTestNow());

it('legt einen Termin an', function () {
    Livewire::test(Kalender::class)
        ->set('type', CalendarEventType::Arzttermin->value)
        ->set('titel', 'Zahnarzt')
        ->set('beginntAm', '2026-06-15 10:00')
        ->call('speichern')
        ->assertHasNoErrors();

    expect(CalendarEvent::where('titel', 'Zahnarzt')->count())->toBe(1);
});

it('expandiert wöchentliche Termine im angezeigten Monatsfenster', function () {
    // wöchentlich montags ab 01.06.; im Juni 2026 fünf Montage (1,8,15,22,29)
    CalendarEvent::factory()->create([
        'titel' => 'Physio', 'beginnt_am' => '2026-06-01 11:00',
        'recurrence_rule_id' => RecurrenceRule::create([
            'freq' => 'weekly', 'intervall' => 1, 'byday' => [1],
        ])->id,
        'created_by' => $this->user->id,
    ]);

    $vorkommen = Livewire::test(Kalender::class)->set('monat', '2026-06')->instance()->vorkommen();

    expect(collect($vorkommen)->where('titel', 'Physio')->count())->toBe(5);
});

it('verweigert das Binden eines Bewohners aus einem fremden Mandanten', function () {
    $fremderTenant = Tenant::create(['name' => 'B', 'slug' => 'b']);
    app(CurrentTenant::class)->set($fremderTenant);
    $fremderBewohner = Resident::factory()->create(['tenant_id' => $fremderTenant->id]);
    app(CurrentTenant::class)->set($this->tenant);

    Livewire::test(Kalender::class)
        ->set('type', CalendarEventType::Arzttermin->value)
        ->set('titel', 'Zahnarzt')
        ->set('beginntAm', '2026-06-15 10:00')
        ->set('residentId', $fremderBewohner->id)
        ->call('speichern')
        ->assertHasErrors('residentId');

    expect(CalendarEvent::query()->count())->toBe(0);
});
