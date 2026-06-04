<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Qdvs\Models\QdvsExport;

beforeEach(fn () => app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a'])));

it('protokolliert einen Export', function () {
    $e = QdvsExport::create([
        'stichtag' => '2026-02-15', 'spec' => 'csv-v1', 'status' => 'exportiert',
        'bewohner_count' => 12, 'pfad' => 'qdvs/x.csv', 'fehler' => [],
    ]);
    expect($e->status)->toBe('exportiert')->and($e->bewohner_count)->toBe(12);
});
