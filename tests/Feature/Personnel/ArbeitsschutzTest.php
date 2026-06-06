<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Personnel\Enums\NachweisTyp;
use App\Domains\Personnel\Models\Schutznachweis;
use App\Livewire\Personnel\Arbeitsschutz;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('pflegefachkraft');
    Role::findOrCreate('kueche');
    $this->pdl = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->pdl->assignRole('pflegefachkraft');
    $this->mitarbeiter = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->mitarbeiter->employeeProfile()->create(['tenant_id' => $this->tenant->id]);
});

it('berechnet Fälligkeit und Ampel aus Datum + Intervall', function () {
    $gueltig = Schutznachweis::create(['user_id' => $this->mitarbeiter->id, 'typ' => NachweisTyp::Unterweisung, 'datum' => today()->subMonths(2)]);
    $ueberfaellig = Schutznachweis::create(['user_id' => $this->mitarbeiter->id, 'typ' => NachweisTyp::ErsteHilfe, 'datum' => today()->subMonths(30)]);
    $bem = Schutznachweis::create(['user_id' => $this->mitarbeiter->id, 'typ' => NachweisTyp::Bem, 'datum' => today()]);

    expect($gueltig->status())->toBe('gueltig')
        ->and($gueltig->faelligAm()->toDateString())->toBe(today()->subMonths(2)->addMonths(12)->toDateString())
        ->and($ueberfaellig->status())->toBe('ueberfaellig')
        ->and($ueberfaellig->ampel())->toBe('red')
        ->and($bem->status())->toBe('anlassbezogen')
        ->and($bem->faelligAm())->toBeNull();
});

it('erfasst einen Nachweis über die UI', function () {
    $this->actingAs($this->pdl);

    Livewire::test(Arbeitsschutz::class)
        ->set('erf_user', $this->mitarbeiter->id)->set('erf_typ', 'unterweisung')->set('erf_datum', today()->toDateString())
        ->call('erfassen')->assertHasNoErrors();

    expect(Schutznachweis::where('user_id', $this->mitarbeiter->id)->where('typ', NachweisTyp::Unterweisung)->exists())->toBeTrue();
});

it('verwehrt den Zugriff ohne Leitungsrolle', function () {
    $koch = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $koch->assignRole('kueche');
    $this->actingAs($koch);

    Livewire::test(Arbeitsschutz::class)->assertForbidden();
});
