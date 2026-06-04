<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Qdvs\Data\QdvsResidentPackage;
use App\Domains\Qdvs\Specs\CsvQdvsSpec;

it('rendert eine CSV mit Kopfzeile und einer Zeile je Bewohner', function () {
    $tenant = Tenant::make(['name' => 'Haus A', 'ik_nummer' => '260123456']);
    $spec = new CsvQdvsSpec;

    $csv = $spec->render([
        new QdvsResidentPackage('R-1', 1940, 'w', 3, '2023-01-01', ['F00.0'], ['sturz' => true, 'dekubitus' => false]),
    ], $tenant, '2026-02-15');

    $lines = array_values(array_filter(explode("\n", trim($csv))));
    expect($lines)->toHaveCount(2)                       // Header + 1 Datenzeile
        ->and($lines[0])->toContain('pseudonym')
        ->and($lines[1])->toContain('R-1')
        ->and($spec->filename('2026-02-15'))->toBe('qdvs-export-2026-02-15.csv');
});
