<?php

use App\Domains\Accounting\Support\AccountingDefaults;
use App\Domains\Capture\Enums\PositionStatus;
use App\Domains\Capture\Models\LieferscheinAnalyse;
use App\Domains\Capture\Models\LieferscheinPositionVorschlag;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake(config('opcare.media_disk', 'media'));

    $this->tenant = Tenant::create(['name' => 'LS-Test', 'slug' => 'ls-test']);
    app(CurrentTenant::class)->set($this->tenant);
    AccountingDefaults::ensureFor($this->tenant->id);

    Role::findOrCreate('buchhaltung');
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('buchhaltung');
});

// ─── Modell-Casts + Relationen ────────────────────────────────────────────────

it('speichert roh_json und kandidaten als Arrays (round-trip)', function () {
    $analyse = LieferscheinAnalyse::create([
        'tenant_id' => $this->tenant->id,
        'lieferant_text' => 'Muster GmbH',
        'datum' => '2026-01-15',
        'lieferschein_nr' => 'LS-2026-001',
        'roh_json' => ['raw' => 'output', 'items' => [1, 2, 3]],
        'modell' => 'llava:latest',
        'konfidenz' => 0.875,
    ]);

    $pos1 = $analyse->positionen()->create([
        'tenant_id' => $this->tenant->id,
        'text' => 'Handschuhe Größe M',
        'menge' => 10.00,
        'einheit' => 'Packung',
        'einzelpreis' => 4.99,
        'kandidaten' => [['id' => 1, 'score' => 0.9], ['id' => 2, 'score' => 0.7]],
        'konfidenz' => 0.910,
        'status' => PositionStatus::Vorgeschlagen,
    ]);

    $pos2 = $analyse->positionen()->create([
        'tenant_id' => $this->tenant->id,
        'text' => 'Mundschutz FFP2',
        'menge' => 5.00,
        'einheit' => 'Stück',
        'kandidaten' => [],
        'status' => PositionStatus::Bestaetigt,
    ]);

    $freshAnalyse = LieferscheinAnalyse::find($analyse->id);
    expect($freshAnalyse->roh_json)->toBe(['raw' => 'output', 'items' => [1, 2, 3]])
        ->and($freshAnalyse->datum->format('Y-m-d'))->toBe('2026-01-15')
        ->and($freshAnalyse->positionen)->toHaveCount(2);

    $freshPos1 = LieferscheinPositionVorschlag::find($pos1->id);
    expect($freshPos1->kandidaten)->toBe([['id' => 1, 'score' => 0.9], ['id' => 2, 'score' => 0.7]])
        ->and($freshPos1->status)->toBe(PositionStatus::Vorgeschlagen)
        ->and($freshPos1->offen())->toBeTrue();

    $freshPos2 = LieferscheinPositionVorschlag::find($pos2->id);
    expect($freshPos2->status)->toBe(PositionStatus::Bestaetigt)
        ->and($freshPos2->offen())->toBeFalse();
});

it('offen() ist false bei Status Verworfen', function () {
    $analyse = LieferscheinAnalyse::create([
        'tenant_id' => $this->tenant->id,
        'lieferant_text' => 'Test',
    ]);

    $pos = $analyse->positionen()->create([
        'tenant_id' => $this->tenant->id,
        'text' => 'Position X',
        'status' => PositionStatus::Verworfen,
    ]);

    expect($pos->offen())->toBeFalse();
});

// ─── Media-Download: signierte URL (tenant-scoped) ────────────────────────────

it('Lieferschein-Download gibt 200 für eigenen Mandanten zurück', function () {
    // WHY(GC-FALLE): Variable muss bis nach Assertion leben.
    $file = UploadedFile::fake()->create('ls.jpg', 120, 'image/jpeg');

    $analyse = LieferscheinAnalyse::create([
        'tenant_id' => $this->tenant->id,
        'lieferant_text' => 'Download-Test',
    ]);

    $analyse->addMedia($file->getRealPath())->usingFileName('ls.jpg')->toMediaCollection('lieferschein');
    $media = $analyse->getFirstMedia('lieferschein');
    expect($media)->not->toBeNull();

    $url = URL::temporarySignedRoute('media.download', now()->addMinutes(5), ['media' => $media->id]);

    $this->actingAs($this->user)
        ->get($url)
        ->assertOk();

    unset($file);
});

it('Lieferschein-Download gibt 403 für fremden Mandanten (IDOR)', function () {
    // WHY(GC-FALLE): Variable muss bis nach Assertion leben.
    $file = UploadedFile::fake()->create('ls_fremd.jpg', 120, 'image/jpeg');

    $analyse = LieferscheinAnalyse::create([
        'tenant_id' => $this->tenant->id,
        'lieferant_text' => 'IDOR-Test',
    ]);

    $analyse->addMedia($file->getRealPath())->usingFileName('ls_fremd.jpg')->toMediaCollection('lieferschein');

    unset($file);

    $media = $analyse->getFirstMedia('lieferschein');
    expect($media)->not->toBeNull();

    $fremdTenant = Tenant::create(['name' => 'LS-Fremd', 'slug' => 'ls-fremd']);
    AccountingDefaults::ensureFor($fremdTenant->id);
    $fremdUser = User::factory()->create(['tenant_id' => $fremdTenant->id]);
    $fremdUser->assignRole('buchhaltung');
    app(CurrentTenant::class)->set($fremdTenant);

    $url = URL::temporarySignedRoute('media.download', now()->addMinutes(5), ['media' => $media->id]);

    $this->actingAs($fremdUser)
        ->get($url)
        ->assertForbidden();
});
