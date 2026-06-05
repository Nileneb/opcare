<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\IcdCode;
use App\Domains\Masterdata\Models\Resident;
use App\Livewire\ResidentShow;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'pflegefachkraft', 'pflegehilfskraft', 'leserecht'] as $r) {
        Role::findOrCreate($r);
    }
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
    $this->user = User::factory()->create(['tenant_id' => $t->id]);
    $this->user->assignRole('admin');

    IcdCode::insert([
        ['code' => 'I10', 'bezeichnung' => 'Essentielle (primäre) Hypertonie'],
        ['code' => 'F00.0', 'bezeichnung' => 'Demenz bei Alzheimer-Krankheit'],
        ['code' => 'E11.9', 'bezeichnung' => 'Diabetes mellitus, Typ 2'],
    ]);
    $this->resident = Resident::factory()->create();
});

it('findet ICD-Codes per Code-Präfix und per Freitext', function () {
    Livewire::actingAs($this->user)->test(ResidentShow::class, ['resident' => $this->resident])
        ->set('diag_search', 'I10')->assertSee('Hypertonie')
        ->set('diag_search', 'demenz')->assertSee('F00.0')->assertDontSee('Hypertonie');
});

it('zeigt unter zwei Zeichen keine Treffer (kein Volltabellen-Dump)', function () {
    Livewire::actingAs($this->user)->test(ResidentShow::class, ['resident' => $this->resident])
        ->set('diag_search', 'I')->assertDontSee('Hypertonie');
});

it('wählt einen Code aus und legt die Diagnose an', function () {
    $id = IcdCode::where('code', 'I10')->value('id');

    Livewire::actingAs($this->user)->test(ResidentShow::class, ['resident' => $this->resident])
        ->call('selectDiagnosis', $id)
        ->assertSet('diag_icd', $id)
        ->assertSet('diag_search', '')
        ->call('addDiagnosis')
        ->assertHasNoErrors();

    expect($this->resident->diagnoses()->count())->toBe(1)
        ->and($this->resident->diagnoses()->first()->icd_code_id)->toBe($id);
});
