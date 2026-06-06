<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Quality\Enums\FemArt;
use App\Domains\Quality\Enums\FemEinwilligung;
use App\Domains\Quality\Models\FemFall;
use App\Livewire\Quality\FemUebersicht;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('pflegefachkraft');
    Role::findOrCreate('kueche');
    $this->pfk = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->pfk->assignRole('pflegefachkraft');
    $this->resident = Resident::factory()->create(['status' => 'aktiv']);
});

function femFall(array $attr = []): FemFall
{
    return FemFall::create(array_merge([
        'resident_id' => test()->resident->id, 'art' => FemArt::Bettgitter, 'anlass' => 'Sturzgefahr',
        'mildere_begruendung' => 'Niederflurbett nicht verfügbar', 'anordnung_arzt' => 'Dr. Meier', 'anordnung_am' => now(),
        'einwilligungsstatus' => FemEinwilligung::GenehmigungErteilt,
    ], $attr));
}

it('leitet Status und Ampel aus Befristung ab', function () {
    expect(femFall(['gueltig_bis' => today()->addMonths(6)])->status())->toBe('genehmigt')
        ->and(femFall(['gueltig_bis' => today()->addDays(10)])->status())->toBe('ueberpruefung_faellig')
        ->and(femFall(['gueltig_bis' => today()->subDay()])->status())->toBe('abgelaufen')
        ->and(femFall(['gueltig_bis' => today()->subDay()])->ampel())->toBe('red')
        ->and(femFall(['einwilligungsstatus' => FemEinwilligung::BewohnerEingewilligt, 'gueltig_bis' => null])->status())->toBe('einwilligung');
});

it('legt einen genehmigten Fall an und verlangt Aktenzeichen + Befristung', function () {
    $this->actingAs($this->pfk);

    Livewire::test(FemUebersicht::class)
        ->set('f_resident', $this->resident->id)->set('f_art', 'bettgitter')->set('f_anlass', 'nächtliche Stürze')
        ->set('f_mildere', ['Niederflurbett'])->set('f_mildere_begruendung', 'nicht ausreichend')->set('f_arzt', 'Dr. Meier')
        ->set('f_einwilligung', 'genehmigt')->set('f_aktenzeichen', '')->set('f_beschluss_am', null)->set('f_gueltig_bis', null)
        ->call('anlegen')->assertHasErrors(['f_aktenzeichen', 'f_beschluss_am', 'f_gueltig_bis']);

    Livewire::test(FemUebersicht::class)
        ->set('f_resident', $this->resident->id)->set('f_art', 'bettgitter')->set('f_anlass', 'nächtliche Stürze')
        ->set('f_mildere', ['Niederflurbett'])->set('f_mildere_begruendung', 'nicht ausreichend')->set('f_arzt', 'Dr. Meier')
        ->set('f_einwilligung', 'genehmigt')->set('f_aktenzeichen', 'XVII 123/26')
        ->set('f_beschluss_am', today()->toDateString())->set('f_gueltig_bis', today()->addYear()->toDateString())
        ->call('anlegen')->assertHasNoErrors();

    expect(FemFall::where('resident_id', $this->resident->id)->first()->aktenzeichen)->toBe('XVII 123/26');
});

it('protokolliert und beendet einen Fall', function () {
    $this->actingAs($this->pfk);
    $fall = femFall(['gueltig_bis' => today()->addYear()]);

    Livewire::test(FemUebersicht::class)->set('selected', $fall->id)
        ->set('p_typ', 'kontrolle')->set('p_befund', 'ruhig')->set('p_indikation', true)->call('protokollieren')->assertHasNoErrors()
        ->set('beend_grund', 'Indikation entfallen')->call('beenden')->assertHasNoErrors();

    expect($fall->fresh()->aktiv())->toBeFalse()
        ->and($fall->protokolle()->count())->toBe(2); // Kontrolle + Beendigung
});

it('verwehrt den Zugriff ohne Pflegefachrolle', function () {
    $koch = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $koch->assignRole('kueche');
    $this->actingAs($koch);
    Livewire::test(FemUebersicht::class)->assertForbidden();
});
