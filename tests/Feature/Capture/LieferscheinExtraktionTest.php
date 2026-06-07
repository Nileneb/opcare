<?php

use App\Domains\Capture\Contracts\LieferscheinVlmAnalyzer;
use App\Domains\Capture\Data\LieferscheinExtraktion;
use App\Domains\Capture\Testing\FakeLieferscheinAnalyzer;

beforeEach(function () {
    config(['speech.fake' => true]);
    app()->bind(LieferscheinVlmAnalyzer::class, FakeLieferscheinAnalyzer::class);
});

it('liefert korrekte Lieferantendaten aus dem Fake-Analyzer', function () {
    $ext = app(LieferscheinVlmAnalyzer::class)->analysiere('x', 'image/jpeg');

    expect($ext->lieferant)->toBe('Großhandel Bergisch GmbH')
        ->and($ext->lieferschein_nr)->toBe('LS-2026-0815')
        ->and($ext->konfidenz)->toBe(0.9);
});

it('liefert zwei Positionen mit korrekten Feldern', function () {
    $ext = app(LieferscheinVlmAnalyzer::class)->analysiere('x', 'image/jpeg');

    expect(count($ext->positionen))->toBe(2);

    $pos0 = $ext->positionen[0];
    expect($pos0->menge)->toBe(10.0)
        ->and($pos0->einheit)->toBe('Sack')
        ->and($pos0->einzelpreis)->toBe(12.5);

    $pos1 = $ext->positionen[1];
    expect($pos1->charge_nr)->toBe('CH-A1')
        ->and($pos1->mhd)->not->toBeNull();
});

it('normalisiert Teil-Daten ohne Fehler (fehlende Felder → null/[])', function () {
    $ext = LieferscheinExtraktion::vonRoh([
        'lieferant' => 'Testfirma',
        'konfidenz' => 0.5,
        // datum, lieferschein_nr, positionen fehlen absichtlich
    ]);

    expect($ext->lieferant)->toBe('Testfirma')
        ->and($ext->datum)->toBeNull()
        ->and($ext->lieferschein_nr)->toBeNull()
        ->and($ext->positionen)->toBe([]);
});

it('normalisiert Positionen mit fehlenden Unterfeldern', function () {
    $ext = LieferscheinExtraktion::vonRoh([
        'lieferant' => null,
        'konfidenz' => 0.3,
        'positionen' => [
            ['text' => 'Artikel A'],
            ['text' => 'Artikel B', 'menge' => '5', 'charge_nr' => 'X'],
        ],
    ]);

    expect(count($ext->positionen))->toBe(2)
        ->and($ext->positionen[0]->menge)->toBeNull()
        ->and($ext->positionen[0]->charge_nr)->toBeNull()
        ->and($ext->positionen[1]->menge)->toBe(5.0)
        ->and($ext->positionen[1]->charge_nr)->toBe('X');
});
