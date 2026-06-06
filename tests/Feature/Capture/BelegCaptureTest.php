<?php

use App\Domains\Accounting\Enums\KontoTyp;
use App\Domains\Accounting\Models\Buchung;
use App\Domains\Accounting\Models\Konto;
use App\Domains\Capture\Contracts\BelegVlmAnalyzer;
use App\Domains\Capture\Data\BelegExtraktion;
use App\Domains\Capture\Enums\VorschlagStatus;
use App\Domains\Capture\Enums\ZielTyp;
use App\Domains\Capture\Models\EinsortierungsVorschlag;
use App\Domains\Capture\Services\BelegCapture;
use App\Domains\Capture\Testing\FakeBelegAnalyzer;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Capture\Belegerfassung;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('media');
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    app()->bind(BelegVlmAnalyzer::class, FakeBelegAnalyzer::class);
    Role::findOrCreate('buchhaltung');
    Role::findOrCreate('kueche');
    $this->kasse = Konto::create(['nummer' => '1000', 'name' => 'Kasse', 'typ' => KontoTyp::Aktiv]);
    $this->aufwand = Konto::create(['nummer' => '5400', 'name' => 'Wareneingang', 'typ' => KontoTyp::Aufwand]);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('buchhaltung');
});

it('analysiert ein Belegfoto und legt einen buchbaren Vorschlag an', function () {
    $file = UploadedFile::fake()->image('beleg.jpg');
    $analyse = app(BelegCapture::class)->erfasse($file->getRealPath(), 'beleg.jpg', 'image/jpeg', $this->user->id);

    expect($analyse->roh_json['betrag'])->toBe(24.9)
        ->and($analyse->vorschlaege)->toHaveCount(1);

    $v = $analyse->vorschlaege->first();
    expect($v->ziel_typ)->toBe(ZielTyp::BuchhaltungBeleg)
        ->and($v->status)->toBe(VorschlagStatus::Vorgeschlagen)
        ->and($v->ziel_felder['betrag'])->toBe(24.9);
});

it('markiert einen Beleg ohne Betrag als unklar (kein geratenes Ziel)', function () {
    app()->bind(BelegVlmAnalyzer::class, fn () => new class implements BelegVlmAnalyzer
    {
        public function analysiere(string $imageBase64, string $mimeType): BelegExtraktion
        {
            return new BelegExtraktion(belegtyp: 'foto', betrag: null, konfidenz: 0.3);
        }
    });

    $file = UploadedFile::fake()->image('x.jpg');
    $analyse = app(BelegCapture::class)->erfasse($file->getRealPath(), 'x.jpg', 'image/jpeg', $this->user->id);

    expect($analyse->vorschlaege->first()->ziel_typ)->toBe(ZielTyp::Unklar);
});

it('bucht erst nach Bestätigung über die Buchen-Action', function () {
    $this->actingAs($this->user);
    Livewire::test(Belegerfassung::class)
        ->set('bild', UploadedFile::fake()->image('beleg.jpg'))
        ->call('analysieren')->assertHasNoErrors();

    $v = EinsortierungsVorschlag::first();
    expect(Buchung::count())->toBe(0); // noch nichts gebucht

    Livewire::test(Belegerfassung::class)
        ->call('bestaetigenStart', $v->id)
        ->set('c_soll', $this->aufwand->id)->set('c_haben', $this->kasse->id)
        ->set('c_text', 'Drogerie-Beleg')->set('c_datum', today()->toDateString())
        ->call('bestaetigen')->assertHasNoErrors();

    $b = Buchung::first();
    expect($b)->not->toBeNull()
        ->and((float) $b->betrag)->toBe(24.9)
        ->and($v->fresh()->status)->toBe(VorschlagStatus::Bestaetigt)
        ->and($v->fresh()->buchung_id)->toBe($b->id);
});

it('verwirft einen Vorschlag ohne zu buchen', function () {
    $file = UploadedFile::fake()->image('b.jpg');
    app(BelegCapture::class)->erfasse($file->getRealPath(), 'b.jpg', 'image/jpeg', $this->user->id);
    $v = EinsortierungsVorschlag::first();

    $this->actingAs($this->user);
    Livewire::test(Belegerfassung::class)->call('verwerfen', $v->id);

    expect($v->fresh()->status)->toBe(VorschlagStatus::Verworfen)
        ->and(Buchung::count())->toBe(0);
});

it('verwehrt den Zugriff ohne Finanzrolle', function () {
    $koch = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $koch->assignRole('kueche');
    $this->actingAs($koch);
    Livewire::test(Belegerfassung::class)->assertForbidden();
});
