<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Quality\Enums\QmBereich;
use App\Domains\Quality\Enums\QmStatus;
use App\Domains\Quality\Models\QmRequirement;
use App\Domains\Quality\Support\QmKatalogDefaults;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
});

it('seedet den QM-Katalog idempotent über alle Bereiche', function () {
    $first = QmKatalogDefaults::ensureFor($this->tenant->id);
    $again = QmKatalogDefaults::ensureFor($this->tenant->id);

    $anzahl = count(QmKatalogDefaults::rules());
    expect($first)->toHaveCount($anzahl)
        ->and($again)->toHaveCount($anzahl)
        ->and($first->pluck('bereich')->unique())->toHaveCount(count(QmBereich::cases()))
        ->and($first->firstWhere('schluessel', 'hyg_masern')->gesetz_url)->toContain('ifsg/__20');
});

it('startet jede Standard-Anforderung mit Status offen', function () {
    $rules = QmKatalogDefaults::ensureFor($this->tenant->id);

    expect($rules->every(fn (QmRequirement $r) => $r->status === QmStatus::Offen))->toBeTrue()
        ->and($rules->first()->bereich)->toBeInstanceOf(QmBereich::class);
});

it('rechnet erledigte Status (erfüllt + nicht zutreffend) korrekt', function () {
    expect(QmStatus::Erfuellt->erledigt())->toBeTrue()
        ->and(QmStatus::NichtZutreffend->erledigt())->toBeTrue()
        ->and(QmStatus::Offen->erledigt())->toBeFalse()
        ->and(QmStatus::InArbeit->erledigt())->toBeFalse();
});

it('ist mandantengetrennt', function () {
    QmKatalogDefaults::ensureFor($this->tenant->id);
    $fremd = Tenant::create(['name' => 'B', 'slug' => 'b']);
    app(CurrentTenant::class)->set($fremd);

    expect(QmRequirement::count())->toBe(0);
});
