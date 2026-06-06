<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Personnel\Enums\Masernschutz;
use App\Domains\Personnel\Enums\Qualifikation;
use App\Domains\Personnel\Enums\Steuerklasse;
use App\Domains\Personnel\Models\EmployeeProfile;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Sandra Vogt']);
});

it('legt eine Personalakte 1:1 zum Benutzer an', function () {
    $this->user->employeeProfile()->create([
        'personalnummer' => 'A-0815', 'vorname' => 'Sandra', 'nachname' => 'Vogt',
        'qualifikation' => Qualifikation::Pflegefachkraft, 'wochenstunden' => 38.5,
        'steuerklasse' => Steuerklasse::IV, 'masernschutz' => Masernschutz::Geimpft,
    ]);

    $profile = $this->user->fresh()->employeeProfile;
    expect($profile)->not->toBeNull()
        ->and($profile->qualifikation)->toBe(Qualifikation::Pflegefachkraft)
        ->and($profile->qualifikation->istFachkraft())->toBeTrue()
        ->and($profile->steuerklasse)->toBe(Steuerklasse::IV)
        ->and($profile->wochenstunden)->toBe(38.5)
        ->and($profile->masernschutz->erfuellt())->toBeTrue();
});

it('verschlüsselt sensible Felder (Steuer-ID, SV-Nummer, IBAN) at rest', function () {
    $this->user->employeeProfile()->create([
        'steuer_id' => '12345678901', 'sv_nummer' => '65170839J003', 'iban' => 'DE89370400440532013000',
    ]);

    $profile = EmployeeProfile::first();
    // Über das Model: Klartext (entschlüsselt)
    expect($profile->steuer_id)->toBe('12345678901')
        ->and($profile->iban)->toBe('DE89370400440532013000');

    // In der DB: NICHT im Klartext (verschlüsselt)
    $raw = DB::table('employee_profiles')->where('id', $profile->id)->first();
    expect($raw->steuer_id)->not->toBe('12345678901')
        ->and($raw->sv_nummer)->not->toBe('65170839J003')
        ->and($raw->iban)->not->toContain('DE89370400440532013000');
});

it('ist mandantengetrennt (fremde Personalakte unsichtbar)', function () {
    $this->user->employeeProfile()->create(['vorname' => 'Sandra']);

    $fremd = Tenant::create(['name' => 'B', 'slug' => 'b']);
    app(CurrentTenant::class)->set($fremd);

    expect(EmployeeProfile::count())->toBe(0);
});
