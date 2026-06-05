<?php

use App\Domains\CarePlanning\Actions\ImportMeasureCatalog;
use App\Domains\CarePlanning\Models\MeasureCatalogItem;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Livewire\ResidentShow;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('importiert den gebündelten Maßnahmen-Katalog (229 Einträge)', function () {
    $count = app(ImportMeasureCatalog::class)->handle(database_path(ImportMeasureCatalog::BUNDLED));

    expect($count)->toBe(229)
        ->and(MeasureCatalogItem::count())->toBe(229)
        ->and(MeasureCatalogItem::where('bezeichnung', 'like', '%Gehübungen%')->exists())->toBeTrue();
});

it('ist idempotent — zweiter Import erzeugt keine Duplikate', function () {
    $action = app(ImportMeasureCatalog::class);
    $action->handle(database_path(ImportMeasureCatalog::BUNDLED));
    $action->handle(database_path(ImportMeasureCatalog::BUNDLED));

    expect(MeasureCatalogItem::count())->toBe(229);
});

it('wirft bei fehlender Datei', function () {
    app(ImportMeasureCatalog::class)->handle('/nicht/vorhanden.csv');
})->throws(RuntimeException::class);

describe('Maßnahmen-Picker in der Planung', function () {
    beforeEach(function () {
        foreach (['admin', 'pflegefachkraft', 'pflegehilfskraft', 'leserecht'] as $r) {
            Role::findOrCreate($r);
        }
        $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
        app(CurrentTenant::class)->set($t);
        $this->user = User::factory()->create(['tenant_id' => $t->id]);
        $this->user->assignRole('admin');
        MeasureCatalogItem::insert([
            ['bezeichnung' => 'Gehübungen'],
            ['bezeichnung' => 'Proph.Dekub. Seite 30°, links'],
        ]);
        $this->resident = Resident::factory()->create();
    });

    it('findet Katalog-Maßnahmen per Freitext', function () {
        Livewire::actingAs($this->user)->test(ResidentShow::class, ['resident' => $this->resident])
            ->set('m_katalog_search', 'dekub')->assertSee('Proph.Dekub')
            ->set('m_katalog_search', 'geh')->assertSee('Gehübungen');
    });

    it('übernimmt eine Katalog-Maßnahme in die Beschreibung', function () {
        $id = MeasureCatalogItem::where('bezeichnung', 'Gehübungen')->value('id');

        Livewire::actingAs($this->user)->test(ResidentShow::class, ['resident' => $this->resident])
            ->set('m_katalog_search', 'geh')
            ->call('pickMeasure', $id)
            ->assertSet('m_beschreibung', 'Gehübungen')
            ->assertSet('m_katalog_search', '');
    });
});
