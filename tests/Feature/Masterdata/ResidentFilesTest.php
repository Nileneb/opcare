<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('media');
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('hängt eine Datei an einen Bewohner', function () {
    $resident = Resident::factory()->create();
    $resident->addMedia(UploadedFile::fake()->create('befund.pdf', 10))
        ->toMediaCollection('documents');

    expect($resident->getMedia('documents'))->toHaveCount(1);
});
