<?php

use App\Domains\Compliance\Enums\Rechtsgrundlage;
use App\Domains\Compliance\Models\Auftragsverarbeitung;
use App\Domains\Compliance\Models\Verarbeitungstaetigkeit;
use App\Domains\Compliance\Services\Art30Export;
use App\Domains\Compliance\Support\VvtDefaults;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Compliance\Datenschutz;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'Haus A', 'slug' => 'haus-a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('admin');
    Role::findOrCreate('kueche');
    $this->leitung = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->leitung->assignRole('admin');
});

it('seedet das Standard-VVT mit Gesundheitsdaten als besondere Kategorie', function () {
    $vts = VvtDefaults::ensureFor($this->tenant->id);
    expect($vts)->toHaveCount(count(VvtDefaults::katalog()));

    $doku = $vts->firstWhere('schluessel', 'pflegedokumentation');
    expect($doku->rechtsgrundlage)->toBe(Rechtsgrundlage::Gesundheitsdaten)
        ->and($doku->rechtsgrundlage->besondereKategorie())->toBeTrue()
        ->and($doku->rechtsgrundlage->artikel())->toContain('Art. 9');
});

it('berechnet die Prüf-Ampel je Aktualität', function () {
    $ungeprueft = Verarbeitungstaetigkeit::create([
        'tenant_id' => $this->tenant->id, 'name' => 'X', 'zweck' => 'z', 'rechtsgrundlage' => Rechtsgrundlage::Vertrag,
        'kategorien_betroffene' => 'b', 'kategorien_daten' => 'd', 'loeschfrist' => '1 J', 'pruef_intervall_monate' => 12,
    ]);
    expect($ungeprueft->status())->toBe('ungeprueft')->and($ungeprueft->ampel())->toBe('red');

    $ungeprueft->update(['geprueft_am' => today()]);
    expect($ungeprueft->fresh()->ampel())->toBe('green');

    $ungeprueft->update(['geprueft_am' => today()->subMonths(13)]);
    expect($ungeprueft->fresh()->status())->toBe('ueberfaellig');

    $ungeprueft->update(['geprueft_am' => today()->subMonths(12)->addDays(20)]);
    expect($ungeprueft->fresh()->status())->toBe('faellig');
});

it('legt eine Verarbeitungstätigkeit an und markiert sie als geprüft', function () {
    $this->actingAs($this->leitung);
    Livewire::test(Datenschutz::class)
        ->set('v_name', 'Spendenverwaltung')->set('v_zweck', 'Verwaltung von Spenden')
        ->set('v_betroffene', 'Spender:innen')->set('v_daten', 'Kontaktdaten')
        ->set('v_loeschfrist', '10 Jahre')->set('v_intervall', 12)
        ->call('verarbeitungAnlegen')->assertHasNoErrors();

    $vt = Verarbeitungstaetigkeit::where('name', 'Spendenverwaltung')->first();
    expect($vt)->not->toBeNull()->and($vt->geprueft_am->toDateString())->toBe(today()->toDateString());
});

it('markiert eine Auftragsverarbeitung ohne Vertrag rot', function () {
    $avv = Auftragsverarbeitung::create([
        'tenant_id' => $this->tenant->id, 'dienstleister' => 'Hoster', 'zweck' => 'Hosting', 'kategorien_daten' => 'alle',
    ]);
    expect($avv->status())->toBe('kein_avv')->and($avv->ampel())->toBe('red');

    $avv->update(['vertrag_geschlossen_am' => today()]);
    expect($avv->fresh()->status())->toBe('aktuell')->and($avv->fresh()->ampel())->toBe('green');
});

it('erzeugt das vorlagefähige Art-30-Verzeichnis als Text', function () {
    VvtDefaults::ensureFor($this->tenant->id);
    Auftragsverarbeitung::create([
        'tenant_id' => $this->tenant->id, 'dienstleister' => 'Cloud GmbH', 'zweck' => 'Hosting', 'kategorien_daten' => 'alle',
    ]);

    $text = app(Art30Export::class)->render($this->tenant->id, 'Haus A');

    expect($text)->toContain('Art. 30 DSGVO')
        ->toContain('Verantwortlicher: Haus A')
        ->toContain('Pflege- und Betreuungsdokumentation')
        ->toContain('Art. 9 Abs. 2 lit. h DSGVO')
        ->toContain('Auftragsverarbeitungen (Art. 28 DSGVO)')
        ->toContain('Cloud GmbH')
        ->toContain('KEIN AVV');
});

it('verwehrt den Zugriff ohne Leitungsrolle', function () {
    $koch = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $koch->assignRole('kueche');
    $this->actingAs($koch);
    Livewire::test(Datenschutz::class)->assertForbidden();
});
