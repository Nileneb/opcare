<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Enums\ShiftKind;
use App\Domains\Scheduling\Models\ComplianceJustification;
use App\Domains\Scheduling\Models\Shift;
use App\Domains\Scheduling\Models\ShiftAssignment;
use App\Livewire\Scheduling\Dienstplan;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('admin');
    Role::findOrCreate('leserecht');
    $this->shift = Shift::create(['name' => 'Früh', 'kind' => ShiftKind::Frueh, 'beginn' => '06:00', 'ende' => '14:00']);
    $this->lang = Shift::create(['name' => 'Langdienst', 'kind' => ShiftKind::Frueh, 'beginn' => '06:00', 'ende' => '19:00']); // 13 h
    $this->montag = Carbon::parse('today')->startOfWeek()->toDateString();
});

function leitung(int $tenantId): User
{
    $u = User::factory()->create(['tenant_id' => $tenantId]);
    $u->assignRole('admin');

    return $u;
}

it('verweigert Pflegekraft mit nur Leserecht die Dienstplan-Pflege', function () {
    $pfk = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $pfk->assignRole('leserecht');
    $this->actingAs($pfk);

    Livewire::test(Dienstplan::class)->assertForbidden();
});

it('lässt die Leitung eine Schicht im Wochen-Grid zuweisen', function () {
    $mitarbeiter = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs(leitung($this->tenant->id));

    Livewire::test(Dienstplan::class)
        ->call('pick', $mitarbeiter->id, $this->montag)
        ->call('zuweisen', $this->shift->id)
        ->assertHasNoErrors();

    expect(ShiftAssignment::where('user_id', $mitarbeiter->id)->count())->toBe(1);
});

it('verweigert das Zuweisen eines Mitarbeiters aus einem fremden Mandanten', function () {
    $this->actingAs(leitung($this->tenant->id));
    $fremd = Tenant::create(['name' => 'B', 'slug' => 'b']);
    $fremderUser = User::factory()->create(['tenant_id' => $fremd->id]);

    Livewire::test(Dienstplan::class)
        ->call('pick', $fremderUser->id, $this->montag)
        ->call('zuweisen', $this->shift->id)
        ->assertHasErrors('userId');

    expect(ShiftAssignment::query()->count())->toBe(0);
});

it('warnt live vor einem 13-h-Dienst (§ 3 Verstoß) und zählt ihn als offen', function () {
    $mitarbeiter = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs(leitung($this->tenant->id));

    Livewire::test(Dienstplan::class)
        ->call('pick', $mitarbeiter->id, $this->montag)
        ->call('zuweisen', $this->lang->id)
        ->assertViewHas('offeneVerstoesse', fn ($n) => $n >= 1)
        ->assertSee('Höchstarbeitszeit');
});

it('dokumentiert einen Verstoß per § 14-Begründung (zählt dann nicht mehr als offen)', function () {
    $mitarbeiter = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs(leitung($this->tenant->id));
    ShiftAssignment::create(['tenant_id' => $this->tenant->id, 'user_id' => $mitarbeiter->id, 'shift_id' => $this->lang->id, 'dienst_am' => $this->montag]);

    Livewire::test(Dienstplan::class)
        ->call('begruendeStart', 'tageshoechstarbeitszeit', $mitarbeiter->id, $this->montag)
        ->set('grund', 'Nachfolgekraft nicht erschienen, Bewohner nicht unbeaufsichtigt.')
        ->call('begruendeSpeichern')
        ->assertHasNoErrors()
        ->assertViewHas('offeneVerstoesse', 0);

    expect(ComplianceJustification::where('user_id', $mitarbeiter->id)->where('rule_key', 'tageshoechstarbeitszeit')->count())->toBe(1);
});
