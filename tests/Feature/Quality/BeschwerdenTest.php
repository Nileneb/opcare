<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Quality\Enums\BeschwerdeStatus;
use App\Domains\Quality\Models\Beschwerde;
use App\Domains\Quality\Notifications\BeschwerdeWeitergeleitet;
use App\Livewire\Quality\Beschwerden;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    foreach (['pflegefachkraft', 'kueche'] as $r) {
        Role::findOrCreate($r);
    }
    $this->qm = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->qm->assignRole('pflegefachkraft');
    $this->actingAs($this->qm);
});

it('erfasst einen Eingang', function () {
    Livewire::test(Beschwerden::class)
        ->set('b_titel', 'Essen zu kalt')
        ->set('b_beschreibung', 'Mehrfach kaltes Mittagessen')
        ->set('b_bereich', 'kueche')
        ->call('erfassen')->assertHasNoErrors();

    $b = Beschwerde::firstOrFail();
    expect($b->titel)->toBe('Essen zu kalt');
    expect($b->melder_user_id)->toBe($this->qm->id);
});

it('speichert bei anonymer Meldung keine Melder-Identität', function () {
    Livewire::test(Beschwerden::class)
        ->set('b_titel', 'Anonym')->set('b_beschreibung', 'x')
        ->set('b_sichtbarkeit', 'anonym')->set('b_melder_name', 'Soll verworfen werden')
        ->call('erfassen')->assertHasNoErrors();

    $b = Beschwerde::firstOrFail();
    expect($b->melder_user_id)->toBeNull();
    expect($b->melder_name)->toBeNull();
    expect($b->anonym())->toBeTrue();
});

it('verlangt einen Schweregrad beim Gewaltvorfall', function () {
    Livewire::test(Beschwerden::class)
        ->set('b_titel', 'Vorfall')->set('b_beschreibung', 'x')
        ->set('b_kategorie', 'gewaltvorfall')
        ->call('erfassen')->assertHasErrors('b_schweregrad');
});

it('leitet namentlich an die Küche weiter, benachrichtigt die Bereichsrolle und protokolliert', function () {
    Notification::fake();
    $kueche = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $kueche->assignRole('kueche');
    $b = Beschwerde::create([
        'tenant_id' => $this->tenant->id, 'titel' => 'X', 'beschreibung' => 'y', 'kategorie' => 'beschwerde',
        'bereich' => 'leitung', 'quelle' => 'bewohner', 'melder_sichtbarkeit' => 'namentlich', 'melder_name' => 'Hr. Test',
        'eingang_am' => today()->toDateString(), 'status' => BeschwerdeStatus::Eingegangen,
    ]);

    Livewire::test(Beschwerden::class)
        ->set('selected', $b->id)
        ->set('w_bereich', 'kueche')
        ->set('w_text', 'Bitte prüfen')
        ->call('weiterleiten')->assertHasNoErrors();

    $b->refresh();
    expect($b->status)->toBe(BeschwerdeStatus::Weitergeleitet);
    $vorgang = $b->vorgaenge()->where('art', 'weiterleitung')->firstOrFail();
    expect($vorgang->an_bereich)->toBe('kueche');
    expect($vorgang->anonym)->toBeFalse();
    Notification::assertSentTo($kueche, BeschwerdeWeitergeleitet::class);
});

it('erzwingt Anonymität bei der Weiterleitung, wenn der Melder anonym gewählt hat', function () {
    $kueche = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $kueche->assignRole('kueche');
    $b = Beschwerde::create([
        'tenant_id' => $this->tenant->id, 'titel' => 'X', 'beschreibung' => 'y', 'kategorie' => 'beschwerde',
        'bereich' => 'leitung', 'quelle' => 'bewohner', 'melder_sichtbarkeit' => 'anonym',
        'eingang_am' => today()->toDateString(), 'status' => BeschwerdeStatus::Eingegangen,
    ]);

    // w_anonym bleibt false — die Wahl des Melders muss dennoch greifen.
    Livewire::test(Beschwerden::class)
        ->set('selected', $b->id)->set('w_bereich', 'kueche')->set('w_anonym', false)
        ->call('weiterleiten')->assertHasNoErrors();

    expect($b->vorgaenge()->where('art', 'weiterleitung')->firstOrFail()->anonym)->toBeTrue();
});

it('verbietet die Weiterleitung ohne Verwaltungsrecht', function () {
    $koch = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $koch->assignRole('kueche');
    $b = Beschwerde::create([
        'tenant_id' => $this->tenant->id, 'titel' => 'X', 'beschreibung' => 'y', 'kategorie' => 'beschwerde',
        'bereich' => 'leitung', 'quelle' => 'bewohner', 'melder_sichtbarkeit' => 'namentlich',
        'eingang_am' => today()->toDateString(), 'status' => BeschwerdeStatus::Eingegangen,
    ]);

    Livewire::actingAs($koch)->test(Beschwerden::class)
        ->set('selected', $b->id)->set('w_bereich', 'pflege')
        ->call('weiterleiten')->assertForbidden();
});
