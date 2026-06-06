<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Enums\Aufgabenkreis;
use App\Domains\Masterdata\Enums\EreignisKategorie;
use App\Domains\Masterdata\Enums\VertretungTyp;
use App\Domains\Masterdata\Models\BewohnerEreignis;
use App\Domains\Masterdata\Models\Custodian;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Masterdata\Notifications\BewohnerEreignisGemeldet;
use App\Livewire\Masterdata\Vertretungen;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    foreach (['pflegefachkraft', 'kueche', 'betreuer', 'angehoeriger'] as $r) {
        Role::findOrCreate($r);
    }
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('pflegefachkraft');
    $this->actingAs($this->user);
    $this->resident = Resident::create(['name' => 'Wilhelm', 'geburtsdatum' => '1940-01-01',
        'geschlecht' => 'm', 'aufnahme_am' => '2020-01-01']);
});

it('legt eine Vertretung mit Aufgabenkreisen an', function () {
    Livewire::test(Vertretungen::class)
        ->set('v_resident_id', $this->resident->id)
        ->set('v_typ', VertretungTyp::GesetzlicherBetreuer->value)
        ->set('v_kreise', [Aufgabenkreis::Gesundheitssorge->value, Aufgabenkreis::Vermoegenssorge->value])
        ->set('v_name', 'RA Vormund')
        ->set('v_bericht_intervall', 12)
        ->call('vertretungAnlegen')->assertHasNoErrors();

    $v = Custodian::where('name', 'RA Vormund')->firstOrFail();
    expect($v->hatAufgabenkreis(Aufgabenkreis::Gesundheitssorge))->toBeTrue();
    expect($v->aufgabenkreise)->toContain(Aufgabenkreis::Vermoegenssorge->value);
});

it('benachrichtigt nur berechtigte Vertretungen mit Konto beim Ereignis', function () {
    Notification::fake();
    $repUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
    Custodian::create(['resident_id' => $this->resident->id, 'user_id' => $repUser->id,
        'typ' => VertretungTyp::GesetzlicherBetreuer->value, 'name' => 'Konto-Betreuer',
        'aufgabenkreise' => [Aufgabenkreis::Gesundheitssorge->value]]);
    // Nicht berechtigt (nur Vermögenssorge) → keine MD-Benachrichtigung
    $other = User::factory()->create(['tenant_id' => $this->tenant->id]);
    Custodian::create(['resident_id' => $this->resident->id, 'user_id' => $other->id,
        'typ' => VertretungTyp::Bevollmaechtigter->value, 'name' => 'Vermögen',
        'aufgabenkreise' => [Aufgabenkreis::Vermoegenssorge->value]]);

    Livewire::test(Vertretungen::class)
        ->set('e_resident_id', $this->resident->id)
        ->set('e_kategorie', EreignisKategorie::MdBegutachtung->value)
        ->set('e_titel', 'MD-Begutachtung')
        ->set('e_datum', now()->toDateString())
        ->call('ereignisErfassen')->assertHasNoErrors();

    expect(BewohnerEreignis::where('titel', 'MD-Begutachtung')->exists())->toBeTrue();
    Notification::assertSentTo($repUser, BewohnerEreignisGemeldet::class);
    Notification::assertNotSentTo($other, BewohnerEreignisGemeldet::class);
});

it('legt ein Login-Konto an und verknüpft es', function () {
    $v = Custodian::create(['resident_id' => $this->resident->id, 'typ' => VertretungTyp::GesetzlicherBetreuer->value,
        'name' => 'RA Vormund', 'email' => 'vormund@example.test',
        'aufgabenkreise' => [Aufgabenkreis::Gesundheitssorge->value]]);

    Livewire::test(Vertretungen::class)->call('kontoAnlegen', $v->id)->assertHasNoErrors();

    $user = User::where('email', 'vormund@example.test')->firstOrFail();
    expect($user->hasRole('betreuer'))->toBeTrue();
    expect($v->fresh()->user_id)->toBe($user->id);
});

it('verbietet Schreibaktionen ohne Verwaltungsrecht', function () {
    $koch = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $koch->assignRole('kueche');
    Livewire::actingAs($koch)->test(Vertretungen::class)
        ->set('v_resident_id', $this->resident->id)
        ->set('v_name', 'X')
        ->call('vertretungAnlegen')->assertForbidden();
});
