<?php

use App\Domains\Masterdata\Actions\ImportIcdCatalog;
use App\Domains\Masterdata\Models\IcdCode;

it('importiert den gebündelten ICD-10-GM-Katalog (2017, 15.930 Codes)', function () {
    $count = app(ImportIcdCatalog::class)->handle(database_path(ImportIcdCatalog::BUNDLED));

    expect($count)->toBe(15930)
        ->and(IcdCode::count())->toBe(15930)
        ->and(IcdCode::where('code', 'I10')->value('bezeichnung'))->toContain('Hypertonie');
});

it('ist idempotent — zweiter Import erzeugt keine Duplikate', function () {
    $action = app(ImportIcdCatalog::class);
    $action->handle(database_path(ImportIcdCatalog::BUNDLED));
    $action->handle(database_path(ImportIcdCatalog::BUNDLED));

    expect(IcdCode::count())->toBe(15930);
});

it('erkennt das amtliche BfArM-syst_kodes-Format (Code=Feld 7, Bezeichnung=Feld 9)', function () {
    $file = tempnam(sys_get_temp_dir(), 'icd');
    file_put_contents($file,
        "3;N;X;01;A00;A00.-;A00;A00;Cholera;Cholera;;;V;V\n".
        "4;T;X;01;A00;A00.0;A00.0;A000;Cholera durch Vibrio cholerae O:1;Cholera;;;P;P\n"
    );

    $count = app(ImportIcdCatalog::class)->handle($file);
    unlink($file);

    expect($count)->toBe(2)
        ->and(IcdCode::where('code', 'A00.0')->value('bezeichnung'))->toBe('Cholera durch Vibrio cholerae O:1');
});

it('wirft bei fehlender Datei', function () {
    app(ImportIcdCatalog::class)->handle('/nicht/vorhanden.csv');
})->throws(RuntimeException::class);
