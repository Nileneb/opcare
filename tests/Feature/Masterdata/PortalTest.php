<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Enums\Aufgabenkreis;
use App\Domains\Masterdata\Enums\VertretungTyp;
use App\Domains\Masterdata\Models\Custodian;
use App\Domains\Masterdata\Models\IcdCode;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Masterdata\Models\ResidentDiagnosis;
use App\Livewire\Masterdata\Portal;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    foreach (['betreuer', 'angehoeriger', 'pflegefachkraft'] as $r) {
        Role::findOrCreate($r);
    }
    $this->resident = Resident::create(['name' => 'Wilhelm', 'geburtsdatum' => '1940-01-01',
        'geschlecht' => 'm', 'aufnahme_am' => '2020-01-01']);
    $icd = IcdCode::firstOrCreate(['code' => 'I10'], ['bezeichnung' => 'Essentielle Hypertonie']);
    ResidentDiagnosis::create(['resident_id' => $this->resident->id, 'icd_code_id' => $icd->id, 'art' => 'haupt']);

    $this->rep = User::factory()->create(['tenant_id' => $this->tenant->id, 'two_factor_confirmed_at' => now()]);
    $this->rep->assignRole('betreuer');
});

it('zeigt der Vertretung mit Gesundheitssorge die Diagnosen', function () {
    Custodian::create(['resident_id' => $this->resident->id, 'user_id' => $this->rep->id,
        'typ' => VertretungTyp::GesetzlicherBetreuer->value, 'name' => 'RA Vormund',
        'aufgabenkreise' => [Aufgabenkreis::Gesundheitssorge->value]]);

    Livewire::actingAs($this->rep)->test(Portal::class)
        ->assertSee('Wilhelm')
        ->assertSee('Essentielle Hypertonie');
});

it('verbirgt Gesundheitsdaten ohne den Aufgabenkreis Gesundheitssorge', function () {
    Custodian::create(['resident_id' => $this->resident->id, 'user_id' => $this->rep->id,
        'typ' => VertretungTyp::Bevollmaechtigter->value, 'name' => 'Post-Bevollmächtigter',
        'aufgabenkreise' => [Aufgabenkreis::Postangelegenheiten->value]]);

    Livewire::actingAs($this->rep)->test(Portal::class)
        ->assertSee('Wilhelm')
        ->assertDontSee('Essentielle Hypertonie');
});

it('hält Portal-Konten von den Staff-Routen fern', function () {
    Custodian::create(['resident_id' => $this->resident->id, 'user_id' => $this->rep->id,
        'typ' => VertretungTyp::GesetzlicherBetreuer->value, 'name' => 'RA Vormund',
        'aufgabenkreise' => [Aufgabenkreis::Gesundheitssorge->value]]);

    $this->actingAs($this->rep)->get('/bewohner')->assertRedirect(route('portal'));
    $this->actingAs($this->rep)->get(route('portal'))->assertOk();
});
