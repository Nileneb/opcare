<?php

use App\Domains\CarePlanning\Models\CareReport;
use App\Domains\CarePlanning\Models\SisAssessment;
use App\Domains\CarePlanning\Models\SisTopicFieldEntry;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
    $this->user = User::factory()->create(['tenant_id' => $t->id]);
    $this->resident = Resident::factory()->create();
});

it('verschlüsselt den Pflegeverlauf at-rest, liefert ihn aber im Klartext über das Modell', function () {
    $report = CareReport::create([
        'resident_id' => $this->resident->id, 'created_by' => $this->user->id,
        'datum' => '2026-06-01 08:00:00', 'schicht' => 'frueh', 'text' => 'Streng vertraulicher Verlauf.',
    ]);

    // Modell-Lesezugriff: Klartext
    expect($report->fresh()->text)->toBe('Streng vertraulicher Verlauf.');

    // At-Rest in der DB: KEIN Klartext, aber als Laravel-Chiffretext entschlüsselbar
    $raw = DB::table('care_reports')->where('id', $report->id)->value('text');
    expect($raw)->not->toBe('Streng vertraulicher Verlauf.')
        ->and(Crypt::decryptString($raw))->toBe('Streng vertraulicher Verlauf.');
});

it('verschlüsselt strukturierte SIS-Daten (encrypted:array) und liefert sie als Array zurück', function () {
    $sis = SisAssessment::create([
        'resident_id' => $this->resident->id, 'created_by' => $this->user->id, 'erstellt_am' => '2026-06-01', 'eingangsfrage' => 'Wie geht es?',
    ]);
    $entry = SisTopicFieldEntry::create([
        'sis_assessment_id' => $sis->id, 'themenfeld' => 'mobilitaet',
        'freitext' => 'Eingeschränkt mobil.', 'strukturdaten' => ['hilfsmittel' => 'Rollator'],
    ]);

    expect($entry->fresh()->freitext)->toBe('Eingeschränkt mobil.')
        ->and($entry->fresh()->strukturdaten)->toBe(['hilfsmittel' => 'Rollator']);

    $raw = DB::table('sis_topic_field_entries')->where('id', $entry->id)->value('strukturdaten');
    expect($raw)->not->toContain('Rollator')
        ->and(json_decode(Crypt::decryptString($raw), true))->toBe(['hilfsmittel' => 'Rollator']);
});
