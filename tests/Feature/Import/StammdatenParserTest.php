<?php

use App\Domains\Import\Support\SpaltenAlias;
use App\Domains\Import\Support\StammdatenParser;

describe('StammdatenParser', function () {
    it('parses semicolon CSV with BOM and maps aliases', function () {
        $csv = "\xEF\xBB\xBF"."Bezeichnung;Einheit;Anfangsbestand;EK\nMehl;kg;50;2,00\nButter;Stück;40;1,79\n";
        $result = StammdatenParser::parseCsv($csv);

        expect($result['mapping']['name'])->toBe('Bezeichnung')
            ->and($result['mapping']['einheit'])->toBe('Einheit')
            ->and($result['mapping']['bestand'])->toBe('Anfangsbestand')
            ->and($result['mapping']['einkaufspreis'])->toBe('EK')
            ->and($result['zeilen'])->toHaveCount(2)
            ->and($result['zeilen'][0]['Bezeichnung'])->toBe('Mehl')
            ->and($result['zeilen'][0]['Anfangsbestand'])->toBe('50');
    });

    it('detects comma delimiter and maps name and lieferant', function () {
        $csv = "Name,Lieferant\nSchraube,Baumarkt Meier\n";
        $result = StammdatenParser::parseCsv($csv);

        expect($result['mapping']['name'])->toBe('Name')
            ->and($result['mapping']['lieferant'])->toBe('Lieferant')
            ->and($result['zeilen'][0]['Lieferant'])->toBe('Baumarkt Meier');
    });

    it('returns null for unknown columns like pg_nummer and mhd', function () {
        $csv = "Name,Einheit\nBandage,Stück\n";
        $result = StammdatenParser::parseCsv($csv);

        expect($result['mapping']['pg_nummer'])->toBeNull()
            ->and($result['mapping']['mhd'])->toBeNull();
    });

    it('C1: erkennt Tab-Delimiter und liest Zeilen korrekt', function () {
        $csv = "Name\tEinheit\tBestand\nMehl\tkg\t50\n";
        $result = StammdatenParser::parseCsv($csv);

        expect($result['zeilen'])->toHaveCount(1)
            ->and($result['zeilen'][0]['Name'])->toBe('Mehl')
            ->and($result['zeilen'][0]['Einheit'])->toBe('kg')
            ->and($result['zeilen'][0]['Bestand'])->toBe('50');
    });

    it('C1: erkennt Pipe-Delimiter', function () {
        $csv = "Name|Einheit|Bestand\nButter|Stück|20\n";
        $result = StammdatenParser::parseCsv($csv);

        expect($result['zeilen'])->toHaveCount(1)
            ->and($result['zeilen'][0]['Name'])->toBe('Butter');
    });

    it('C1: bei Gleichstand Semikolon vor Komma bevorzugt', function () {
        $csv = "A;B,C\nX;Y,Z\n";
        $result = StammdatenParser::parseCsv($csv);

        expect($result['header'])->toContain('A');
    });
});

describe('SpaltenAlias::erkenne', function () {
    it('recognises aliased headers case-insensitively', function () {
        $mapping = SpaltenAlias::erkenne(['Artikelbezeichnung', 'EK-Preis']);

        expect($mapping['name'])->toBe('Artikelbezeichnung')
            ->and($mapping['einkaufspreis'])->toBe('EK-Preis');
    });

    it('returns null for unmatched target fields', function () {
        $mapping = SpaltenAlias::erkenne(['Artikelbezeichnung', 'EK-Preis']);

        expect($mapping['pg_nummer'])->toBeNull()
            ->and($mapping['mhd'])->toBeNull()
            ->and($mapping['charge_nr'])->toBeNull();
    });
});
