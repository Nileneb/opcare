<?php

use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Vision\Contracts\VisionClient;
use App\Domains\Vision\Models\ProductLabel;
use App\Domains\Vision\Models\RegalAufnahme;
use App\Domains\Vision\Models\RegalDetection;
use App\Domains\Vision\Testing\FakeVisionClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Storage::fake('media');

    config(['vision.fake' => true]);

    $this->tenant = Tenant::create(['name' => 'Vision-Tenant', 'slug' => 'vision-t']);
    app(CurrentTenant::class)->set($this->tenant);

    $this->artikel = Artikel::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Einweghandschuh Box',
        'einheit' => 'Stk',
        'abteilung' => Abteilung::Pflege,
        'bestand' => 100,
    ]);
});

// ─── Binding ──────────────────────────────────────────────────────────────────

it('löst FakeVisionClient auf, wenn vision.fake=true', function () {
    expect(app(VisionClient::class))->toBeInstanceOf(FakeVisionClient::class);
});

// ─── FakeVisionClient ─────────────────────────────────────────────────────────

it('detect gibt counts[box]=3 zurück', function () {
    $result = app(VisionClient::class)->detect('x', '/m/v.pt');

    expect($result['counts']['box'])->toBe(3)
        ->and($result['model_used'])->toBe('fake');
});

it('autoAnnotate gibt eine Suggestion zurück', function () {
    $result = app(VisionClient::class)->autoAnnotate('x');

    expect($result['suggestions'])->toHaveCount(1);
});

it('train gibt job-fake-1 zurück', function () {
    expect(app(VisionClient::class)->train('z64', 'tenant-ref'))->toBe('job-fake-1');
});

it('trainStatus gibt completed zurück', function () {
    $status = app(VisionClient::class)->trainStatus('job-fake-1');

    expect($status['status'])->toBe('completed')
        ->and($status['class_names'])->toContain('box');
});

// ─── ProductLabel ─────────────────────────────────────────────────────────────

it('mengeFuer multipliziert count * multiplier korrekt', function () {
    $label = ProductLabel::create([
        'tenant_id' => $this->tenant->id,
        'yolo_label' => 'box',
        'artikel_id' => $this->artikel->id,
        'multiplier' => 12,
    ]);

    expect($label->mengeFuer(3))->toBe(36.0);
});

it('ProductLabel->artikel() gibt den verknüpften Artikel zurück', function () {
    $label = ProductLabel::create([
        'tenant_id' => $this->tenant->id,
        'yolo_label' => 'box',
        'artikel_id' => $this->artikel->id,
        'multiplier' => 1,
    ]);

    expect($label->artikel->id)->toBe($this->artikel->id);
});

// ─── RegalAufnahme + RegalDetection ───────────────────────────────────────────

it('RegalAufnahme kann RegalDetektionen anlegen und Relation laden', function () {
    $aufnahme = RegalAufnahme::create([
        'tenant_id' => $this->tenant->id,
        'notiz' => 'Test-Regal',
    ]);

    RegalDetection::create([
        'tenant_id' => $this->tenant->id,
        'aufnahme_id' => $aufnahme->id,
        'label' => 'box',
        'confidence' => 0.9125,
        'artikel_id' => $this->artikel->id,
        'menge_vorschlag' => 12.50,
    ]);

    $det = $aufnahme->fresh()->detektionen->first();

    expect($det)->not->toBeNull()
        ->and($det->label)->toBe('box')
        ->and((float) $det->confidence)->toBe(0.9125)
        ->and((float) $det->menge_vorschlag)->toBe(12.50)
        ->and($det->artikel->id)->toBe($this->artikel->id)
        ->and($det->aufnahme->id)->toBe($aufnahme->id);
});

// ─── Media-Download 200 eigener / 403 fremder Tenant ─────────────────────────

it('RegalAufnahme-Foto-Download gibt 200 für eigenen Tenant', function () {
    // WHY(GC-FALLE): $fotoFile muss bis nach Assertion im Scope bleiben.
    $fotoFile = UploadedFile::fake()->image('regal.jpg');

    $aufnahme = RegalAufnahme::create([
        'tenant_id' => $this->tenant->id,
        'notiz' => 'Shelf-A',
    ]);
    $aufnahme->addMedia($fotoFile->getRealPath())
        ->usingFileName('regal.jpg')
        ->toMediaCollection('foto');

    $media = $aufnahme->getFirstMedia('foto');
    expect($media)->not->toBeNull();

    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $url = URL::temporarySignedRoute('media.download', now()->addMinutes(5), ['media' => $media->id]);

    $this->actingAs($user)->get($url)->assertOk();

    unset($fotoFile);
});

it('RegalAufnahme-Foto-Download gibt 403 für fremden Tenant (IDOR)', function () {
    // WHY(GC-FALLE): $fotoFile muss bis nach Assertion im Scope bleiben.
    $fotoFile = UploadedFile::fake()->image('regal_fremd.jpg');

    $aufnahme = RegalAufnahme::create([
        'tenant_id' => $this->tenant->id,
        'notiz' => 'Shelf-B',
    ]);
    $aufnahme->addMedia($fotoFile->getRealPath())
        ->usingFileName('regal_fremd.jpg')
        ->toMediaCollection('foto');

    $media = $aufnahme->getFirstMedia('foto');
    expect($media)->not->toBeNull();

    unset($fotoFile);

    $fremdTenant = Tenant::create(['name' => 'Fremd-Vision', 'slug' => 'fremd-vis']);
    $fremdUser = User::factory()->create(['tenant_id' => $fremdTenant->id]);
    app(CurrentTenant::class)->set($fremdTenant);

    $url = URL::temporarySignedRoute('media.download', now()->addMinutes(5), ['media' => $media->id]);

    $this->actingAs($fremdUser)->get($url)->assertForbidden();
});

// ─── Tenant-Scope: Fremd-Tenant-Artikel nicht sichtbar ───────────────────────

it('ProductLabel eines fremden Tenants nicht über eigenen Scope erreichbar', function () {
    $fremdTenant = Tenant::create(['name' => 'Fremd-Scope', 'slug' => 'fremd-scope']);
    $fremdArtikel = Artikel::create([
        'tenant_id' => $fremdTenant->id,
        'name' => 'Fremd-Artikel',
        'einheit' => 'Stk',
        'abteilung' => Abteilung::Pflege,
        'bestand' => 0,
    ]);

    // Label im fremden Tenant — eigener CurrentTenant bleibt this->tenant.
    $fremdLabel = ProductLabel::withoutGlobalScopes()->create([
        'tenant_id' => $fremdTenant->id,
        'yolo_label' => 'fremd-box',
        'artikel_id' => $fremdArtikel->id,
        'multiplier' => 5,
    ]);

    // Unter eigenem Tenant darf das Label nicht gefunden werden.
    expect(ProductLabel::find($fremdLabel->id))->toBeNull();
});
