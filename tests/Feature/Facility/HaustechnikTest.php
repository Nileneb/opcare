<?php

use App\Domains\Facility\Enums\AssetKategorie;
use App\Domains\Facility\Enums\MeldungStatus;
use App\Domains\Facility\Models\FacilityAsset;
use App\Domains\Facility\Models\FacilityMeldung;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Facility\Haustechnik;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    foreach (['haustechnik', 'pflegehilfskraft'] as $r) {
        Role::findOrCreate($r);
    }
});

function user(int $tenantId, string $role): User
{
    $u = User::factory()->create(['tenant_id' => $tenantId]);
    $u->assignRole($role);

    return $u;
}

it('lässt jede:n Mitarbeitende:n einen Mangel melden', function () {
    $pfh = user($this->tenant->id, 'pflegehilfskraft');
    $this->actingAs($pfh);

    Livewire::test(Haustechnik::class)
        ->set('m_titel', 'Heizung Zimmer 7 defekt')
        ->set('m_standort', 'Zimmer 7')
        ->call('melden')
        ->assertHasNoErrors();

    $m = FacilityMeldung::first();
    expect($m->titel)->toBe('Heizung Zimmer 7 defekt')
        ->and($m->gemeldet_von)->toBe($pfh->id)
        ->and($m->status)->toBe(MeldungStatus::Offen);
});

it('verwehrt Nicht-Verwaltern das Bearbeiten der Queue', function () {
    $pfh = user($this->tenant->id, 'pflegehilfskraft');
    $this->actingAs($pfh);
    $m = FacilityMeldung::create(['titel' => 'X', 'gemeldet_von' => $pfh->id]);

    Livewire::test(Haustechnik::class)->call('uebernehmen', $m->id)->assertForbidden();
});

it('lässt die Haustechnik die Meldung übernehmen und erledigen', function () {
    $ht = user($this->tenant->id, 'haustechnik');
    $this->actingAs($ht);
    $m = FacilityMeldung::create(['titel' => 'Tür klemmt', 'gemeldet_von' => $ht->id]);

    Livewire::test(Haustechnik::class)
        ->call('uebernehmen', $m->id)
        ->call('erledigenStart', $m->id)
        ->set('erledigt_notiz', 'Scharnier geölt')
        ->call('erledigen')
        ->assertHasNoErrors();

    $fresh = FacilityMeldung::find($m->id);
    expect($fresh->status)->toBe(MeldungStatus::Erledigt)
        ->and($fresh->erledigt_am)->not->toBeNull()
        ->and($fresh->erledigt_notiz)->toBe('Scharnier geölt');
});

it('dokumentiert eine Prüfung (setzt letzte_pruefung auf heute)', function () {
    $ht = user($this->tenant->id, 'haustechnik');
    $this->actingAs($ht);
    $asset = FacilityAsset::create(['bezeichnung' => 'Aufzug', 'kategorie' => AssetKategorie::Aufzug, 'pruefintervall_monate' => 12, 'letzte_pruefung' => now()->subYears(2)->toDateString()]);
    expect($asset->ueberfaellig())->toBeTrue();

    Livewire::test(Haustechnik::class)->call('geprueft', $asset->id);

    expect(FacilityAsset::find($asset->id)->letzte_pruefung->isToday())->toBeTrue();
});
