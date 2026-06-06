<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Personnel\Enums\Qualifikation;
use App\Domains\Personnel\Models\Delegation;
use App\Domains\Personnel\Models\MitarbeiterKompetenz;
use App\Domains\Personnel\Support\Befugnis;
use App\Domains\Personnel\Support\KompetenzDefaults;
use App\Domains\Personnel\Support\TaetigkeitDefaults;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->taetigkeiten = TaetigkeitDefaults::ensureFor($this->tenant->id)->keyBy('key');
    $this->kompetenzen = KompetenzDefaults::ensureFor($this->tenant->id)->keyBy('key');
    $this->befugnis = app(Befugnis::class);

    $this->fachkraft = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->fachkraft->employeeProfile()->create(['tenant_id' => $this->tenant->id, 'qualifikation' => Qualifikation::Pflegefachkraft]);
    $this->hilfskraft = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->hilfskraft->employeeProfile()->create(['tenant_id' => $this->tenant->id, 'qualifikation' => Qualifikation::Pflegehilfskraft]);
});

it('sperrt Vorbehaltsaufgaben für Nicht-Fachkräfte (§ 4 PflBG)', function () {
    $sis = $this->taetigkeiten['sis_abzeichnen'];
    expect($this->befugnis->darf($this->fachkraft, $sis))->toBeTrue()
        ->and($this->befugnis->hindernis($this->hilfskraft, $sis))->toContain('§ 4 PflBG');
});

it('verlangt die erforderliche Zusatzkompetenz (SC-Injektion + Delegation)', function () {
    $sc = $this->taetigkeiten['sc_injektion']; // braucht Kompetenz sc_injektion + ärztl. Delegation

    // Hilfskraft ohne alles: Kompetenz fehlt
    expect($this->befugnis->hindernis($this->hilfskraft, $sc))->toContain('Kompetenz');

    // Kompetenz erteilen → jetzt fehlt die Delegation
    MitarbeiterKompetenz::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->hilfskraft->id,
        'kompetenz_id' => $this->kompetenzen['sc_injektion']->id, 'erworben_am' => today()]);
    expect($this->befugnis->hindernis($this->hilfskraft->fresh(), $sc))->toContain('Delegation');

    // Delegation erteilen → darf
    Delegation::create(['tenant_id' => $this->tenant->id, 'taetigkeit_id' => $sc->id, 'nehmer_id' => $this->hilfskraft->id,
        'anordner_name' => 'Dr. Meier', 'delegiert_am' => today(), 'gueltig_bis' => today()->addYear()]);
    expect($this->befugnis->darf($this->hilfskraft->fresh(), $sc))->toBeTrue();
});

it('eine abgelaufene Delegation zählt für Hilfskräfte nicht', function () {
    $sc = $this->taetigkeiten['sc_injektion'];
    MitarbeiterKompetenz::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->hilfskraft->id,
        'kompetenz_id' => $this->kompetenzen['sc_injektion']->id, 'erworben_am' => today()]);
    Delegation::create(['tenant_id' => $this->tenant->id, 'taetigkeit_id' => $sc->id, 'nehmer_id' => $this->hilfskraft->id,
        'anordner_name' => 'Dr. Meier', 'delegiert_am' => today()->subYears(2), 'gueltig_bis' => today()->subDay()]);
    expect($this->befugnis->hindernis($this->hilfskraft->fresh(), $sc))->toContain('Delegation');
});

it('Fachkraft darf delegationspflichtige Tätigkeit ohne expliziten Delegationssatz (Verordnung)', function () {
    expect($this->befugnis->darf($this->fachkraft, $this->taetigkeiten['iv_injektion']))->toBeTrue();
});

it('BEEP-Heilkunde verlangt auch von Fachkräften die heilkundliche Qualifikation', function () {
    $beep = $this->taetigkeiten['beep_wunde'];
    // normale Fachkraft (ohne B.Sc.-heilkundlich) → blockiert
    expect($this->befugnis->hindernis($this->fachkraft, $beep))->toContain('Kompetenz');
    // mit B.Sc.-heilkundlich → darf
    MitarbeiterKompetenz::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->fachkraft->id,
        'kompetenz_id' => $this->kompetenzen['bsc_pflege_heilkundlich']->id, 'erworben_am' => today()]);
    expect($this->befugnis->darf($this->fachkraft->fresh(), $beep))->toBeTrue();
});

it('eine ist_fachkraft-Kompetenz begründet den Fachkraft-Status', function () {
    $sis = $this->taetigkeiten['sis_abzeichnen'];
    // Hilfskraft erhält die Grundberuf-Kompetenz „Pflegefachkraft" → gilt als Fachkraft
    MitarbeiterKompetenz::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->hilfskraft->id,
        'kompetenz_id' => $this->kompetenzen['pflegefachkraft']->id, 'erworben_am' => today()]);
    expect($this->befugnis->darf($this->hilfskraft->fresh(), $sis))->toBeTrue();
});
