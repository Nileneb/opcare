<?php

use App\Domains\Accounting\Actions\BestellungAnlegen;
use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Lagerschicht;
use App\Domains\Accounting\Models\Lieferant;
use App\Domains\Accounting\Support\AccountingDefaults;
use App\Domains\Capture\Contracts\ArtikelMatcher;
use App\Domains\Capture\Enums\PositionStatus;
use App\Domains\Capture\Models\LieferantArtikelAlias;
use App\Domains\Capture\Services\CaptureWareneingang;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('opcare.media_disk', 'media'));

    config(['speech.fake' => true]);

    $this->tenant = Tenant::create(['name' => 'CW-Test', 'slug' => 'cw-test']);
    app(CurrentTenant::class)->set($this->tenant);
    AccountingDefaults::ensureFor($this->tenant->id);

    $this->tenantId = $this->tenant->id;

    $this->mehl = Artikel::create([
        'tenant_id' => $this->tenantId,
        'name' => 'Weizenmehl Type 405',
        'einheit' => 'Sack',
        'abteilung' => Abteilung::Hauswirtschaft,
        'bestand' => 0,
        'aktiv' => true,
        'pflegehilfsmittel' => false,
        'gefahrstoff' => false,
    ]);

    $this->butter = Artikel::create([
        'tenant_id' => $this->tenantId,
        'name' => 'Markenbutter 250g',
        'einheit' => 'Stück',
        'abteilung' => Abteilung::Hauswirtschaft,
        'bestand' => 0,
        'aktiv' => true,
        'pflegehilfsmittel' => false,
        'gefahrstoff' => false,
    ]);

    $this->service = app(CaptureWareneingang::class);
});

it('erfasse legt LieferscheinAnalyse mit 2 Positionen und Foto an', function () {
    $imageBase64 = base64_encode('fake-image-data');

    $analyse = $this->service->erfasse($imageBase64, 'image/jpeg', $this->tenantId);

    expect($analyse->id)->toBeInt()
        ->and($analyse->lieferant_text)->toBe('Großhandel Bergisch GmbH')
        ->and($analyse->positionen)->toHaveCount(2);

    $positionen = $analyse->positionen;
    expect($positionen[0]->matched_artikel_id)->not->toBeNull()
        ->and($positionen[1]->matched_artikel_id)->not->toBeNull()
        ->and($positionen[0]->kandidaten)->toBeArray()->not->toBeEmpty()
        ->and($positionen[1]->kandidaten)->toBeArray()->not->toBeEmpty()
        ->and($positionen[0]->status)->toBe(PositionStatus::Vorgeschlagen)
        ->and($positionen[1]->status)->toBe(PositionStatus::Vorgeschlagen);

    expect($analyse->getMedia('lieferschein'))->toHaveCount(1);
});

it('erfasse setzt lieferant_id wenn Lieferant im Tenant existiert', function () {
    Lieferant::create([
        'tenant_id' => $this->tenantId,
        'name' => 'Großhandel Bergisch GmbH',
    ]);

    $analyse = $this->service->erfasse(base64_encode('img'), 'image/jpeg', $this->tenantId);

    expect($analyse->lieferant_id)->not->toBeNull();
});

it('bestaetige standalone bucht FIFO-Schicht und setzt Position auf Bestaetigt', function () {
    $analyse = $this->service->erfasse(base64_encode('img'), 'image/jpeg', $this->tenantId);
    $pos = $analyse->positionen->first();

    $artikelId = $this->mehl->id;
    $menge = 5.0;

    $result = $this->service->bestaetige($pos, $artikelId, $menge, 12.50, null, null, null, $this->tenantId);

    expect($result->status)->toBe(PositionStatus::Bestaetigt)
        ->and($result->wareneingang_bewegung_id)->not->toBeNull()
        ->and($result->matched_artikel_id)->toBe($artikelId);

    $this->mehl->refresh();
    expect((float) $this->mehl->bestand)->toBe($menge);

    $schicht = Lagerschicht::where('eingang_bewegung_id', $result->wareneingang_bewegung_id)->first();
    expect($schicht)->not->toBeNull()
        ->and((float) $schicht->menge_rest)->toBe($menge)
        ->and($schicht->lieferant_id)->toBeNull();
});

it('bestaetige standalone überträgt charge_nr und mhd auf Lagerschicht', function () {
    $analyse = $this->service->erfasse(base64_encode('img'), 'image/jpeg', $this->tenantId);
    $pos = $analyse->positionen->last();

    $result = $this->service->bestaetige($pos, $this->butter->id, 10.0, 1.79, 'CH-A1', '2026-07-01', null, $this->tenantId);

    $schicht = Lagerschicht::where('eingang_bewegung_id', $result->wareneingang_bewegung_id)->first();
    expect($schicht->charge_nr)->toBe('CH-A1')
        ->and($schicht->mhd->format('Y-m-d'))->toBe('2026-07-01');
});

it('bestaetige gegen offene Bestellposition erhöht menge_geliefert und setzt bestellposition_id auf Schicht', function () {
    $lieferant = Lieferant::create(['tenant_id' => $this->tenantId, 'name' => 'Großhandel Bergisch GmbH']);

    $bestellung = app(BestellungAnlegen::class)->handle(
        $lieferant->id,
        [['artikel_id' => $this->mehl->id, 'menge' => 20.0, 'preis' => 12.50]],
        null,
    );
    $bestellposition = $bestellung->positionen->first();

    $analyse = $this->service->erfasse(base64_encode('img'), 'image/jpeg', $this->tenantId);

    // Finde die Mehl-Position
    $posVorschlag = $analyse->positionen->first();

    $result = $this->service->bestaetige(
        $posVorschlag,
        $this->mehl->id,
        10.0,
        12.50,
        null,
        null,
        $bestellposition->id,
        $this->tenantId,
    );

    $bestellposition->refresh();
    expect((float) $bestellposition->menge_geliefert)->toBe(10.0);

    $schicht = Lagerschicht::where('eingang_bewegung_id', $result->wareneingang_bewegung_id)->first();
    expect($schicht->bestellposition_id)->toBe($bestellposition->id);
});

it('Über-Lieferung gegen Bestellposition wirft InvalidArgumentException', function () {
    $lieferant = Lieferant::create(['tenant_id' => $this->tenantId, 'name' => 'Lieferant X']);

    $bestellung = app(BestellungAnlegen::class)->handle(
        $lieferant->id,
        [['artikel_id' => $this->mehl->id, 'menge' => 5.0]],
        null,
    );
    $bestellposition = $bestellung->positionen->first();

    $analyse = $this->service->erfasse(base64_encode('img'), 'image/jpeg', $this->tenantId);
    $pos = $analyse->positionen->first();

    expect(fn () => $this->service->bestaetige($pos, $this->mehl->id, 100.0, null, null, null, $bestellposition->id, $this->tenantId))
        ->toThrow(InvalidArgumentException::class);
});

it('bestaetige mit artikelId 0 wirft InvalidArgumentException ohne Buchung', function () {
    $analyse = $this->service->erfasse(base64_encode('img'), 'image/jpeg', $this->tenantId);
    $pos = $analyse->positionen->first();

    expect(fn () => $this->service->bestaetige($pos, 0, 5.0, null, null, null, null, $this->tenantId))
        ->toThrow(InvalidArgumentException::class, 'Artikel erforderlich');

    $pos->refresh();
    expect($pos->status)->toBe(PositionStatus::Vorgeschlagen);
});

it('Gedächtnis lernt: nach bestaetige taucht Artikel in match-Ergebnis auf', function () {
    $analyse = $this->service->erfasse(base64_encode('img'), 'image/jpeg', $this->tenantId);
    $pos = $analyse->positionen->first();

    $this->service->bestaetige($pos, $this->mehl->id, 5.0, null, null, null, null, $this->tenantId);

    $alias = LieferantArtikelAlias::withoutGlobalScopes()
        ->where('tenant_id', $this->tenantId)
        ->where('artikel_id', $this->mehl->id)
        ->first();

    expect($alias)->not->toBeNull()
        ->and($alias->treffer)->toBeGreaterThanOrEqual(1);

    $kandidaten = app(ArtikelMatcher::class)->match($pos->text, $analyse->lieferant_id, $this->tenantId);
    $ids = array_map(fn ($k) => $k->artikel_id, $kandidaten);
    expect($ids)->toContain($this->mehl->id);
});

it('verwerfe setzt Status auf Verworfen', function () {
    $analyse = $this->service->erfasse(base64_encode('img'), 'image/jpeg', $this->tenantId);
    $pos = $analyse->positionen->first();

    $this->service->verwerfe($pos);

    $pos->refresh();
    expect($pos->status)->toBe(PositionStatus::Verworfen)
        ->and($pos->offen())->toBeFalse();
});

it('bestaetige auf bereits bestätigter Position wirft Exception', function () {
    $analyse = $this->service->erfasse(base64_encode('img'), 'image/jpeg', $this->tenantId);
    $pos = $analyse->positionen->first();

    $this->service->bestaetige($pos, $this->mehl->id, 5.0, null, null, null, null, $this->tenantId);

    $pos->refresh();
    expect(fn () => $this->service->bestaetige($pos, $this->mehl->id, 5.0, null, null, null, null, $this->tenantId))
        ->toThrow(InvalidArgumentException::class);
});

// --- Regressionstests Final-Review ---

it('A1: bestaetige mit Bestellposition deren artikel_id ≠ gewähltem Artikel wirft Exception und bucht nicht', function () {
    $lieferant = Lieferant::create(['tenant_id' => $this->tenantId, 'name' => 'Großhandel Bergisch GmbH']);

    $bestellung = app(BestellungAnlegen::class)->handle(
        $lieferant->id,
        [['artikel_id' => $this->mehl->id, 'menge' => 10.0]],
        null,
    );
    $bestellposition = $bestellung->positionen->first();

    // Analyse mit bekanntem Lieferanten, damit lieferant_id gesetzt wird
    $analyse = $this->service->erfasse(base64_encode('img'), 'image/jpeg', $this->tenantId);
    $analyse->update(['lieferant_id' => $lieferant->id]);
    $pos = $analyse->positionen->first();

    // Mismatch: Bestellposition gehört zu Mehl, aber Bediener wählt Butter
    expect(fn () => $this->service->bestaetige(
        $pos->fresh(),
        $this->butter->id,
        5.0,
        null, null, null,
        $bestellposition->id,
        $this->tenantId,
    ))->toThrow(InvalidArgumentException::class, 'Gewählter Artikel passt nicht zur Bestellposition.');

    // Kein Wareneingang gebucht
    $this->mehl->refresh();
    $this->butter->refresh();
    expect((float) $this->mehl->bestand)->toBe(0.0)
        ->and((float) $this->butter->bestand)->toBe(0.0);

    // Kein Alias gelernt
    $alias = LieferantArtikelAlias::withoutGlobalScopes()
        ->where('tenant_id', $this->tenantId)
        ->first();
    expect($alias)->toBeNull();
});

it('B2: bestaetige mit Bestellposition eines anderen Lieferanten wirft Exception', function () {
    $lieferantA = Lieferant::create(['tenant_id' => $this->tenantId, 'name' => 'Großhandel Bergisch GmbH']);
    $lieferantB = Lieferant::create(['tenant_id' => $this->tenantId, 'name' => 'Anderer Lieferant GmbH']);

    // Bestellung unter Lieferant B
    $bestellungB = app(BestellungAnlegen::class)->handle(
        $lieferantB->id,
        [['artikel_id' => $this->mehl->id, 'menge' => 10.0]],
        null,
    );
    $bestellpositionB = $bestellungB->positionen->first();

    // Analyse gehört zu Lieferant A
    $analyse = $this->service->erfasse(base64_encode('img'), 'image/jpeg', $this->tenantId);
    $analyse->update(['lieferant_id' => $lieferantA->id]);
    $pos = $analyse->positionen->first();

    // Bestellposition von Lieferant B gegen Lieferschein von Lieferant A
    expect(fn () => $this->service->bestaetige(
        $pos->fresh(),
        $this->mehl->id,
        5.0,
        null, null, null,
        $bestellpositionB->id,
        $this->tenantId,
    ))->toThrow(InvalidArgumentException::class, 'Bestellposition gehört nicht zum Lieferanten des Belegs.');
});
