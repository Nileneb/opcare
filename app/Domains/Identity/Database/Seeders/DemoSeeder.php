<?php

namespace App\Domains\Identity\Database\Seeders;

use App\Domains\Accounting\Actions\Buchen;
use App\Domains\Accounting\Actions\InventurAbschliessen;
use App\Domains\Accounting\Actions\InventurStarten;
use App\Domains\Accounting\Actions\TreuhandBuchen;
use App\Domains\Accounting\Actions\Wareneingang;
use App\Domains\Accounting\Actions\Warenverbrauch;
use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Enums\BarbetragKategorie;
use App\Domains\Accounting\Enums\TreuhandVorgang;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Budget;
use App\Domains\Accounting\Models\Treuhandbudget;
use App\Domains\Accounting\Models\Treuhandkonto;
use App\Domains\Accounting\Support\AccountingDefaults;
use App\Domains\Assessment\Actions\ConductAssessment;
use App\Domains\Assessment\Data\AssessmentInputData;
use App\Domains\Assessment\Database\Seeders\InstrumentSeeder;
use App\Domains\Assessment\Models\Instrument;
use App\Domains\Capture\Enums\VorschlagStatus;
use App\Domains\Capture\Enums\ZielTyp;
use App\Domains\Capture\Models\BelegAnalyse;
use App\Domains\CarePlanning\Models\CareMeasure;
use App\Domains\CarePlanning\Models\SisAssessment;
use App\Domains\Catering\Enums\EssenswunschArt;
use App\Domains\Catering\Enums\LmivAllergen;
use App\Domains\Catering\Enums\Mahlzeit;
use App\Domains\Catering\Models\Essenswunsch;
use App\Domains\Catering\Models\Gericht;
use App\Domains\Compliance\Models\Auftragsverarbeitung;
use App\Domains\Compliance\Models\Verarbeitungstaetigkeit;
use App\Domains\Compliance\Support\VvtDefaults;
use App\Domains\Facility\Enums\AssetKategorie;
use App\Domains\Facility\Enums\MeldungPrioritaet;
use App\Domains\Facility\Enums\MeldungStatus;
use App\Domains\Facility\Enums\MpAnlage;
use App\Domains\Facility\Enums\MpVorkommnisArt;
use App\Domains\Facility\Models\FacilityAsset;
use App\Domains\Facility\Models\FacilityMeldung;
use App\Domains\Facility\Models\Medizinprodukt;
use App\Domains\Facility\Models\MedizinproduktEinweisung;
use App\Domains\Facility\Models\MedizinproduktVorkommnis;
use App\Domains\Hygiene\Enums\BefundArt;
use App\Domains\Hygiene\Enums\Erreger;
use App\Domains\Hygiene\Models\Hygieneplan;
use App\Domains\Hygiene\Models\InfektionsBefund;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Enums\Aufgabenkreis;
use App\Domains\Masterdata\Enums\EreignisKategorie;
use App\Domains\Masterdata\Enums\VertretungTyp;
use App\Domains\Masterdata\Models\BewohnerEreignis;
use App\Domains\Masterdata\Models\Building;
use App\Domains\Masterdata\Models\Custodian;
use App\Domains\Masterdata\Models\Floor;
use App\Domains\Masterdata\Models\HealthInsurance;
use App\Domains\Masterdata\Models\IcdCode;
use App\Domains\Masterdata\Models\Physician;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Masterdata\Models\ResidentDiagnosis;
use App\Domains\Masterdata\Models\Room;
use App\Domains\Masterdata\Models\Station;
use App\Domains\Medication\Actions\AddSchedule;
use App\Domains\Medication\Actions\AddStock;
use App\Domains\Medication\Actions\BtmBuchen;
use App\Domains\Medication\Actions\CreatePrescription;
use App\Domains\Medication\Actions\GenerateAdministrations;
use App\Domains\Medication\Data\PrescriptionData;
use App\Domains\Medication\Data\ScheduleData;
use App\Domains\Medication\Data\StockData;
use App\Domains\Medication\Database\Seeders\MedicationReferenceSeeder;
use App\Domains\Medication\Enums\BtmVorgang;
use App\Domains\Medication\Enums\ScheduleFrequency;
use App\Domains\Medication\Enums\VitalType;
use App\Domains\Medication\Models\BtmKonto;
use App\Domains\Medication\Models\MedProduct;
use App\Domains\Medication\Models\TradeForm;
use App\Domains\Medication\Models\VitalReading;
use App\Domains\Personnel\Enums\Beschaeftigungsart;
use App\Domains\Personnel\Enums\BetriebsbetreuungArt;
use App\Domains\Personnel\Enums\Energiestufe;
use App\Domains\Personnel\Enums\FortbildungsThema;
use App\Domains\Personnel\Enums\Krankenversicherung;
use App\Domains\Personnel\Enums\Masernschutz;
use App\Domains\Personnel\Enums\NachweisTyp;
use App\Domains\Personnel\Enums\Qualifikation;
use App\Domains\Personnel\Enums\Steuerklasse;
use App\Domains\Personnel\Models\Beauftragtenbestellung;
use App\Domains\Personnel\Models\Betriebsbetreuung;
use App\Domains\Personnel\Models\Delegation;
use App\Domains\Personnel\Models\Energielevel;
use App\Domains\Personnel\Models\Fortbildung;
use App\Domains\Personnel\Models\MitarbeiterKompetenz;
use App\Domains\Personnel\Models\Schutznachweis;
use App\Domains\Personnel\Support\BeauftragtenrolleDefaults;
use App\Domains\Personnel\Support\KompetenzDefaults;
use App\Domains\Personnel\Support\TaetigkeitDefaults;
use App\Domains\Quality\Enums\EventSeverity;
use App\Domains\Quality\Enums\FemArt;
use App\Domains\Quality\Enums\FemEinwilligung;
use App\Domains\Quality\Enums\GremiumTyp;
use App\Domains\Quality\Enums\QmStatus;
use App\Domains\Quality\Enums\QualityIndicator;
use App\Domains\Quality\Models\Beschwerde;
use App\Domains\Quality\Models\CareEvent;
use App\Domains\Quality\Models\FemFall;
use App\Domains\Quality\Models\Gremium;
use App\Domains\Quality\Support\QmKatalogDefaults;
use App\Domains\Scheduling\Compliance\ArbeitszeitgesetzDefaults;
use App\Domains\Scheduling\Compliance\PersonalbemessungDefaults;
use App\Domains\Scheduling\Compliance\ScheduleQualityDefaults;
use App\Domains\Scheduling\Compliance\SpitzenzeitDefaults;
use App\Domains\Scheduling\Database\Seeders\ShiftSeeder;
use App\Domains\Scheduling\Enums\AbwesenheitTyp;
use App\Domains\Scheduling\Enums\ShiftKind;
use App\Domains\Scheduling\Enums\WunschTyp;
use App\Domains\Scheduling\Models\ComplianceJustification;
use App\Domains\Scheduling\Models\Dienstwunsch;
use App\Domains\Scheduling\Models\Shift;
use App\Domains\Scheduling\Models\ShiftAssignment;
use App\Domains\Scheduling\Models\Zeitbuchung;
use App\Domains\Scheduling\Support\ShiftCoverageService;
use App\Domains\SocialCare\Enums\BetreuungsArt;
use App\Domains\SocialCare\Enums\BetreuungsTyp;
use App\Domains\SocialCare\Enums\Handlungsfeld;
use App\Domains\SocialCare\Models\Betreuungsangebot;
use App\Domains\SocialCare\Models\Praeventionsprogramm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        Role::findOrCreate('super-admin');

        $tenant = Tenant::create([
            'name' => 'Bergische Diakonie — Wohnbereich Aprath', 'slug' => 'aprath',
            'ik_nummer' => '260326822',
            'strasse' => 'Aprather Weg', 'hausnummer' => '20', 'plz' => '42489', 'ort' => 'Wülfrath',
        ]);
        app(CurrentTenant::class)->set($tenant);
        $this->call(MedicationReferenceSeeder::class);
        $this->call(ShiftSeeder::class);
        $this->call(InstrumentSeeder::class);

        $admin = User::create([
            'name' => 'Bettina Mertens',
            'email' => 'admin@opcare.local',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant->id,
        ]);
        $admin->assignRole('admin');

        $building = Building::create(['name' => 'Haus Aprath']);
        $floor = Floor::create(['building_id' => $building->id, 'name' => 'Erdgeschoss']);
        $station = Station::create(['floor_id' => $floor->id, 'name' => 'Wohnbereich 1']);

        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super@opcare.local',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant->id,
        ]);
        $superAdmin->assignRole('super-admin');

        foreach ($this->residents() as $r) {
            $room = Room::create(['station_id' => $station->id, 'nummer' => $r['room'], 'betten' => 1]);

            $resident = Resident::create([
                'room_id' => $room->id,
                'name' => $r['name'],
                'geburtsdatum' => now()->subYears($r['age'])->format('Y-m-d'),
                'geschlecht' => $r['geschlecht'],
                'pflegegrad' => $r['pflegegrad'],
                'aufnahme_am' => $r['aufnahme'],
                'status' => 'aktiv',
            ]);

            $sis = SisAssessment::create([
                'resident_id' => $resident->id,
                'created_by' => $admin->id,
                'erstellt_am' => now()->format('Y-m-d'),
                'status' => 'aktiv',
                'eingangsfrage' => $r['eingangsfrage'],
            ]);

            foreach ($r['areas'] as $themenfeld => $area) {
                $sis->topicFields()->create([
                    'themenfeld' => $themenfeld,
                    'freitext' => $area['ressourcen'][0] ?? null,
                    'strukturdaten' => [
                        'status' => $area['status'],
                        'ressourcen' => $area['ressourcen'],
                        'belastungen' => $area['belastungen'],
                        'ziele' => $area['ziele'],
                        'massnahmen' => $area['massnahmen'],
                        'updated' => $area['updated'],
                        'by' => $area['by'],
                    ],
                ]);

                foreach ($area['massnahmen'] as $massnahme) {
                    if ($massnahme === '—') {
                        continue;
                    }
                    CareMeasure::create([
                        'resident_id' => $resident->id,
                        'themenfeld' => $themenfeld,
                        'beschreibung' => $massnahme,
                        'ziel' => $area['ziele'][0] ?? null,
                        'verantwortlich' => $area['by'],
                    ]);
                }
            }
        }

        // WHY: Medikation sofort sichtbar/testbar nach migrate:fresh --seed.
        app(CurrentTenant::class)->set($tenant);
        $tablette = TradeForm::firstOrCreate(['name' => 'Tablette'], ['einheit' => 'Stück', 'teilbar' => true]);
        $ramipril = MedProduct::create([
            'trade_form_id' => $tablette->id,
            'name' => 'Ramipril 5 mg',
            'wirkstoff' => 'Ramipril',
            'staerke' => '5 mg',
            'pzn' => '06313728',
            'btm' => false,
        ]);

        $maria = Resident::query()->where('name', 'Maria Schneider')->first();
        $prescription = app(CreatePrescription::class)->handle(new PrescriptionData(
            resident_id: $maria->id,
            created_by: $admin->id,
            med_product_id: $ramipril->id,
            gueltig_von: Carbon::today()->toDateString(),
        ));

        $schedule = app(AddSchedule::class)->handle($prescription, new ScheduleData(
            frequenz: ScheduleFrequency::Taeglich->value,
            dosis: ['morgens' => 1],
        ));

        app(AddStock::class)->handle(new StockData(
            resident_id: $maria->id,
            med_product_id: $ramipril->id,
            menge: 100,
            einheit: 'Stk',
        ));

        app(GenerateAdministrations::class)->handle(
            $schedule,
            Carbon::today()->toDateString(),
            Carbon::today()->addDays(3)->toDateString(),
        );

        // WHY: kein „Feature ohne Outcome" — Controlling/Report muss nach migrate:fresh sofort Zahlen zeigen.
        app(CurrentTenant::class)->set($tenant);
        $quarterStart = now()->startOfQuarter()->toDateString();

        $maria = Resident::query()->where('name', 'Maria Schneider')->firstOrFail();
        CareEvent::create([
            'resident_id' => $maria->id,
            'indicator' => QualityIndicator::Sturz,
            'datum' => now()->startOfQuarter()->addDays(5)->toDateString(),
            'severity' => EventSeverity::MitFolgen,
            'details' => ['ort' => 'Bad', 'verletzung' => 'Platzwunde Kopf', 'anzahl' => 1, 'fraktur' => false],
            'reported_by' => $admin->id,
        ]);
        CareEvent::create([
            'resident_id' => $maria->id,
            'indicator' => QualityIndicator::Dekubitus,
            'datum' => $quarterStart,
            'behoben_am' => now()->startOfQuarter()->addDays(14)->toDateString(),
            'severity' => EventSeverity::Leicht,
            'details' => ['lokalisation' => 'Steißbein', 'grad' => 1],
            'reported_by' => $admin->id,
        ]);

        // Diagnosen + Vitalwerte für Maria — realistische Demo UND vollständige FHIR-Export-Abdeckung
        foreach (['I10', 'E11.9'] as $i => $code) {
            if ($icd = IcdCode::where('code', $code)->first()) {
                ResidentDiagnosis::create([
                    'resident_id' => $maria->id,
                    'icd_code_id' => $icd->id,
                    'art' => $i === 0 ? 'primär' : 'sekundär',
                    'diagnostiziert_am' => now()->subMonths(8)->toDateString(),
                ]);
            }
        }
        $maria->allergies()->create([
            'substanz' => 'Penicillin', 'typ' => 'allergie', 'kategorie' => 'medikament',
            'kritikalitaet' => 'hoch', 'reaktion' => 'Hautausschlag', 'erfasst_am' => now()->subMonths(8)->toDateString(),
        ]);
        foreach ([[VitalType::Gewicht, 68.5, null, 'kg'], [VitalType::Koerpergroesse, 165, null, 'cm'], [VitalType::Blutdruck, 135, 85, 'mmHg'], [VitalType::Puls, 72, null, '/min']] as [$typ, $wert, $wert2, $einheit]) {
            VitalReading::create([
                'resident_id' => $maria->id,
                'typ' => $typ,
                'wert' => $wert,
                'wert2' => $wert2,
                'einheit' => $einheit,
                'gemessen_am' => now()->subDays(2),
                'gemessen_von' => $admin->id,
            ]);
        }

        // Barthel-Index für Maria (mittlere Hilfsbedürftigkeit) — füllt ÜLB-Sektion funktionsbeurteilungen
        $barthel = Instrument::with('items.options')->where('name', 'Barthel-Index')->first();
        if ($barthel) {
            $answers = $barthel->items->mapWithKeys(function ($item) {
                $opts = $item->options;
                $pick = $opts->get((int) floor(($opts->count() - 1) / 2)) ?? $opts->first();

                return [$item->id => $pick->id];
            })->all();
            app(ConductAssessment::class)->handle(new AssessmentInputData(
                resident_id: $maria->id, instrument_id: $barthel->id, created_by: $admin->id,
                answers: $answers, durchgefuehrt_am: now()->subDays(3)->toDateString(),
            ));
        }

        foreach ([['bewusstsein', '271591004', null], ['harnkontinenz', '450841000', null], ['stuhlkontinenz', '24029004', null], ['kostform', '160670007', null], ['atmung', null, 'unauffällig, keine Atemnot']] as [$typ, $code, $text]) {
            $maria->statusObservations()->create(['typ' => $typ, 'wert_code' => $code, 'wert_text' => $text, 'erfasst_am' => now()->subDays(3)->toDateString()]);
        }
        foreach ([['Rollator', 'hilfsmittel', 'für Strecken > 10 m'], ['Hörgerät rechts', 'hilfsmittel', null]] as [$bez, $kat, $hinweis]) {
            $maria->devices()->create(['bezeichnung' => $bez, 'kategorie' => $kat, 'hinweis' => $hinweis, 'seit' => now()->subMonths(6)->toDateString()]);
        }
        $maria->contacts()->create(['name' => 'Anna Schneider', 'beziehung' => 'Tochter', 'telefon' => '0201 1234567', 'benachrichtigen' => true]);

        $arzt = Physician::create(['name' => 'Dr. Walter Hausarzt', 'fachrichtung' => 'Allgemeinmedizin', 'lanr' => '838382202', 'bsnr' => '031234567', 'kontakt' => '0201 9876543', 'strasse' => 'Praxisweg', 'hausnummer' => '5', 'plz' => '42489', 'ort' => 'Wülfrath']);
        $maria->physicians()->attach($arzt);

        $kasse = HealthInsurance::create(['name' => 'AOK Rheinland/Hamburg', 'ik_nummer' => '104212505']);
        $maria->insurances()->create(['health_insurance_id' => $kasse->id, 'versichertennr' => 'X110411319', 'ist_primaer' => true]);
        $maria->update(['strasse' => 'Bergische Str.', 'hausnummer' => '12', 'plz' => '42489', 'ort' => 'Wülfrath']);

        $wilhelm = Resident::query()->where('name', 'Wilhelm Müller')->firstOrFail();
        CareEvent::create([
            'resident_id' => $wilhelm->id,
            'indicator' => QualityIndicator::Sturz,
            'datum' => now()->startOfQuarter()->addDays(12)->toDateString(),
            'severity' => EventSeverity::OhneFolgen,
            'details' => ['ort' => 'Flur', 'verletzung' => 'keine', 'anzahl' => 1, 'fraktur' => false],
            'reported_by' => $admin->id,
        ]);

        $kurt = Resident::query()->where('name', 'Kurt Petersen')->firstOrFail();
        CareEvent::create([
            'resident_id' => $kurt->id,
            'indicator' => QualityIndicator::Dekubitus,
            'datum' => now()->startOfQuarter()->addDays(3)->toDateString(),
            'severity' => EventSeverity::Schwer,
            'details' => ['lokalisation' => 'Ferse rechts', 'grad' => 3],
            'reported_by' => $admin->id,
        ]);

        // Dienstplan-Demo: Pflegekräfte + Dienste der laufenden Woche, inkl. ArbZG-Befunden (ein offener
        // § 5-Verstoß, ein per § 14 begründeter, ein Sonntags-Hinweis) — füllt das Konformitätspanel.
        ArbeitszeitgesetzDefaults::ensureFor($tenant->id);
        $sandra = User::create(['name' => 'Sandra Vogt', 'email' => 'sandra@opcare.local', 'password' => Hash::make('password'), 'tenant_id' => $tenant->id]);
        $tom = User::create(['name' => 'Tom Berger', 'email' => 'tom@opcare.local', 'password' => Hash::make('password'), 'tenant_id' => $tenant->id]);
        $sandra->employeeProfile()->create([
            'vorname' => 'Sandra', 'nachname' => 'Vogt', 'qualifikation' => Qualifikation::Pflegefachkraft,
            'beschaeftigungsart' => Beschaeftigungsart::Vollzeit, 'wochenstunden' => 38.5, 'position' => 'Pflegefachkraft Wohnbereich 1',
            'eintritt_am' => now()->subYears(3)->toDateString(), 'masernschutz' => Masernschutz::Geimpft,
            'steuerklasse' => Steuerklasse::I, 'krankenversicherung' => Krankenversicherung::GesetzlichPflicht, 'krankenkasse' => 'AOK Rheinland/Hamburg',
        ]);
        $tom->employeeProfile()->create([
            'vorname' => 'Tom', 'nachname' => 'Berger', 'qualifikation' => Qualifikation::Pflegehilfskraft,
            'beschaeftigungsart' => Beschaeftigungsart::Teilzeit, 'wochenstunden' => 25, 'position' => 'Pflegehilfskraft',
            'eintritt_am' => now()->subMonths(8)->toDateString(), 'masernschutz' => Masernschutz::Geimpft,
        ]);
        // Weitere planbare Mitarbeitende (Personalakte) — Basis für Auto-Dienstplan + Arbeitsschutz-Matrix.
        foreach ([
            ['Nina Kraus', 'nina', Qualifikation::Pflegefachkraft, 38.5],
            ['Jens Pohl', 'jens', Qualifikation::Pflegehilfskraft, 30.0],
            ['Lea Brandt', 'lea', Qualifikation::Betreuungskraft, 20.0],
        ] as [$name, $slug, $qual, $std]) {
            [$vn, $nn] = explode(' ', $name, 2);
            $mitarbeiter = User::create(['name' => $name, 'email' => $slug.'@opcare.local', 'password' => Hash::make('password'), 'tenant_id' => $tenant->id]);
            $mitarbeiter->assignRole($qual === Qualifikation::Pflegefachkraft ? 'pflegefachkraft' : ($qual === Qualifikation::Betreuungskraft ? 'betreuungskraft' : 'pflegehilfskraft'));
            $mitarbeiter->employeeProfile()->create([
                'vorname' => $vn, 'nachname' => $nn, 'qualifikation' => $qual,
                'beschaeftigungsart' => $std >= 38 ? Beschaeftigungsart::Vollzeit : Beschaeftigungsart::Teilzeit,
                'wochenstunden' => $std, 'eintritt_am' => now()->subYears(2)->toDateString(), 'masernschutz' => Masernschutz::Geimpft,
            ]);
        }

        $frueh = Shift::query()->where('name', 'Frühdienst')->first();
        $spaet = Shift::query()->where('name', 'Spätdienst')->first();
        $mon = now()->startOfWeek();
        $plan = [
            [$sandra, $frueh, 0], [$sandra, $frueh, 1], [$sandra, $spaet, 2], [$sandra, $frueh, 3], // § 5 Mi→Do (begründet)
            [$tom, $spaet, 0], [$tom, $frueh, 1], [$tom, $frueh, 4], [$tom, $frueh, 6],             // § 5 Mo→Di (offen) + So-Hinweis
        ];
        foreach ($plan as [$mitarbeiter, $schicht, $offset]) {
            ShiftAssignment::create(['tenant_id' => $tenant->id, 'user_id' => $mitarbeiter->id, 'shift_id' => $schicht->id, 'dienst_am' => $mon->copy()->addDays($offset)->toDateString()]);
        }
        ComplianceJustification::create([
            'tenant_id' => $tenant->id, 'user_id' => $sandra->id, 'rule_key' => 'ruhezeit',
            'datum' => $mon->copy()->addDays(3)->toDateString(),
            'grund' => 'Nachfolgekraft kurzfristig erkrankt — Bewohner durften nicht unbeaufsichtigt bleiben (§ 14 ArbZG).',
            'begruendet_von' => $admin->id,
        ]);

        // Wunschdienstplan: Dienstwünsche, die die PDL im Dienstplan-Grid sieht.
        Dienstwunsch::create(['user_id' => $sandra->id, 'datum' => $mon->copy()->addDays(4)->toDateString(), 'typ' => WunschTyp::Frei, 'notiz' => 'Familienfeier']);
        Dienstwunsch::create(['user_id' => $tom->id, 'datum' => $mon->copy()->addDays(2)->toDateString(), 'typ' => WunschTyp::Arbeiten, 'notiz' => 'gern Frühdienst']);
        Dienstwunsch::create(['user_id' => $tom->id, 'datum' => $mon->copy()->addDays(5)->toDateString(), 'typ' => WunschTyp::NichtVerfuegbar]);

        // QM-Norm-Checkliste: Startkatalog + ein paar gepflegte Status für die Demo.
        $qm = QmKatalogDefaults::ensureFor($tenant->id);
        $qmStatus = [
            'hyg_plan' => [QmStatus::Erfuellt, 'Hygienebeauftragte'],
            'hyg_beauftragte' => [QmStatus::Erfuellt, 'PDL'],
            'hyg_masern' => [QmStatus::InArbeit, 'Personalbüro'],
            'qb6_qmsystem' => [QmStatus::Erfuellt, 'QMB'],
            'qb6_beschwerde' => [QmStatus::Erfuellt, 'Heimleitung'],
            'ds_dsb' => [QmStatus::Erfuellt, 'externer DSB'],
            'as_gefaehrdung' => [QmStatus::InArbeit, 'Fachkraft Arbeitssicherheit'],
            'hw_allergene' => [QmStatus::Erfuellt, 'Küchenleitung'],
        ];
        foreach ($qmStatus as $schluessel => [$status, $zustaendig]) {
            $qm->firstWhere('schluessel', $schluessel)?->update([
                'status' => $status, 'zustaendig' => $zustaendig,
                'geprueft_am' => $status === QmStatus::Erfuellt ? now()->subDays(20)->toDateString() : null,
            ]);
        }

        // Haustechnik (DIN 31051): Betriebsmittel mit Prüffristen + Mängelmeldungen für die Demo.
        $hausmeister = User::create(['name' => 'Frank Kessler', 'email' => 'haustechnik@opcare.local', 'password' => Hash::make('password'), 'tenant_id' => $tenant->id]);
        $hausmeister->assignRole('haustechnik');
        $aufzug = FacilityAsset::create(['bezeichnung' => 'Aufzug Haus Aprath', 'kategorie' => AssetKategorie::Aufzug, 'standort' => 'Treppenhaus', 'norm' => 'BetrSichV', 'pruefintervall_monate' => 12, 'letzte_pruefung' => now()->subMonths(14)->toDateString()]);
        FacilityAsset::create(['bezeichnung' => 'Brandmeldeanlage', 'kategorie' => AssetKategorie::Brandschutz, 'standort' => 'gesamt', 'norm' => 'DIN 14675', 'pruefintervall_monate' => 12, 'letzte_pruefung' => now()->subMonths(5)->toDateString()]);
        FacilityAsset::create(['bezeichnung' => 'Pflegebetten WB 1 (8 Stk.)', 'kategorie' => AssetKategorie::Medizinprodukt, 'standort' => 'Wohnbereich 1', 'norm' => 'MPBetreibV', 'pruefintervall_monate' => 24, 'letzte_pruefung' => now()->subMonths(3)->toDateString()]);
        FacilityMeldung::create(['titel' => 'Heizung Zimmer 7 wird nicht warm', 'beschreibung' => 'Thermostat reagiert nicht.', 'standort' => 'Zimmer 7', 'prioritaet' => MeldungPrioritaet::Hoch, 'gemeldet_von' => $admin->id]);
        FacilityMeldung::create(['titel' => 'Türschließer Haupteingang quietscht', 'standort' => 'Eingang EG', 'prioritaet' => MeldungPrioritaet::Niedrig, 'status' => MeldungStatus::InArbeit, 'gemeldet_von' => $sandra->id]);
        FacilityMeldung::create(['titel' => 'Wasserhahn Küche tropft', 'asset_id' => null, 'standort' => 'Küche', 'prioritaet' => MeldungPrioritaet::Mittel, 'status' => MeldungStatus::Erledigt, 'erledigt_am' => now()->subDays(2)->toDateString(), 'erledigt_notiz' => 'Dichtung getauscht.', 'gemeldet_von' => $admin->id]);

        // Medizinprodukte (MPBetreibV § 13/§ 14): Bestandsverzeichnis + Medizinproduktebuch mit STK/MTK-Ampel.
        $defi = Medizinprodukt::create(['bezeichnung' => 'Defibrillator (AED)', 'typ' => 'HeartStart FRx', 'hersteller' => 'Philips', 'seriennummer' => 'A21J-00847', 'inventarnummer' => 'MP-001', 'anschaffungsjahr' => 2021, 'standort' => 'Foyer EG', 'zuordnung' => 'gesamt', 'anlage' => MpAnlage::Anlage1, 'inbetriebnahme_am' => '2021-03-01', 'stk_intervall_monate' => 24, 'letzte_stk' => now()->subMonths(26)->toDateString()]); // STK überfällig → rot
        $bz = Medizinprodukt::create(['bezeichnung' => 'Blutzuckermessgerät', 'typ' => 'Accu-Chek Guide', 'hersteller' => 'Roche', 'seriennummer' => 'ROC-55120', 'inventarnummer' => 'MP-002', 'anschaffungsjahr' => 2023, 'standort' => 'Pflegestützpunkt WB 1', 'zuordnung' => 'Wohnbereich 1', 'anlage' => MpAnlage::Anlage2, 'inbetriebnahme_am' => '2023-06-15', 'mtk_intervall_monate' => 24, 'letzte_mtk' => now()->subMonths(10)->toDateString()]);
        Medizinprodukt::create(['bezeichnung' => 'Pflegebett (elektrisch)', 'typ' => 'Burmeier Dali', 'hersteller' => 'Burmeier', 'seriennummer' => 'BM-2024-3312', 'inventarnummer' => 'MP-003', 'anschaffungsjahr' => 2024, 'standort' => 'Zimmer 12', 'zuordnung' => 'Wohnbereich 1', 'anlage' => MpAnlage::Keine, 'inbetriebnahme_am' => '2024-01-20']);
        MedizinproduktEinweisung::create(['medizinprodukt_id' => $defi->id, 'user_id' => $sandra->id, 'eingewiesen_am' => '2021-03-02', 'eingewiesen_durch' => 'Philips-Medizintechnik', 'art' => 'ersteinweisung']);
        MedizinproduktEinweisung::create(['medizinprodukt_id' => $bz->id, 'user_id' => $sandra->id, 'eingewiesen_am' => '2023-06-16', 'eingewiesen_durch' => 'QMB', 'art' => 'ersteinweisung']);
        MedizinproduktVorkommnis::create(['medizinprodukt_id' => $bz->id, 'datum' => now()->subDays(20)->toDateString(), 'art' => MpVorkommnisArt::Funktionsstoerung, 'beschreibung' => 'Display zeitweise nicht ablesbar.', 'massnahme' => 'Akku getauscht, Funktionsprüfung ok.', 'gemeldet_von' => $sandra->id, 'behoben_am' => now()->subDays(19)->toDateString()]);

        // Küche/Verpflegung (LMIV): Köchin + Lebensmittelallergie + Speiseplan mit Allergen-Warnung.
        $koechin = User::create(['name' => 'Rita Hoffmann', 'email' => 'kueche@opcare.local', 'password' => Hash::make('password'), 'tenant_id' => $tenant->id]);
        $koechin->assignRole('kueche');
        $maria->allergies()->create(['substanz' => 'Erdnüsse', 'typ' => 'allergie', 'kategorie' => 'nahrung', 'kritikalitaet' => 'hoch', 'reaktion' => 'anaphylaktisch', 'erfasst_am' => now()->subYear()->toDateString()]);
        Gericht::create(['datum' => now()->toDateString(), 'mahlzeit' => Mahlzeit::Mittag, 'bezeichnung' => 'Erdnuss-Hähnchen mit Reis', 'allergene' => [LmivAllergen::Erdnuesse->value, LmivAllergen::Soja->value]]);
        $eintopf = Gericht::create(['datum' => now()->toDateString(), 'mahlzeit' => Mahlzeit::Mittag, 'bezeichnung' => 'Gemüseeintopf (vegan)', 'allergene' => [LmivAllergen::Sellerie->value]]);
        Gericht::create(['datum' => now()->toDateString(), 'mahlzeit' => Mahlzeit::Abend, 'bezeichnung' => 'Käsebrot mit Salat', 'allergene' => [LmivAllergen::Milch->value, LmivAllergen::Gluten->value]]);
        // Essenswünsche (Küche sieht sie jederzeit) + Menüwahl (Maria wählt wegen Erdnuss-Allergie den Eintopf).
        Essenswunsch::create(['tenant_id' => $tenant->id, 'resident_id' => $maria->id, 'art' => EssenswunschArt::Abneigung, 'text' => 'kein Fisch']);
        Essenswunsch::create(['tenant_id' => $tenant->id, 'resident_id' => $maria->id, 'art' => EssenswunschArt::Vorliebe, 'text' => 'gern kleine Portionen']);
        $eintopf->menuewahlen()->create(['tenant_id' => $tenant->id, 'resident_id' => $maria->id]);

        // Arbeitszeit-Ist (BAG/EuGH): erfasste Zeiten der laufenden Woche für Sandra/Tom (Soll-Ist-Demo).
        $woStart = now()->startOfWeek();
        foreach ([[$sandra, 0, '06:00', '14:30'], [$sandra, 1, '06:00', '14:15'], [$tom, 0, '13:30', '22:00']] as [$mitarbeiter, $offset, $beginn, $ende]) {
            Zeitbuchung::create(['user_id' => $mitarbeiter->id, 'datum' => $woStart->copy()->addDays($offset)->toDateString(), 'beginn' => $beginn, 'ende' => $ende, 'pause_minuten' => 30]);
        }

        // Soziale Betreuung (§ 43b SGB XI): Betreuungskraft + Angebote des Tages + Teilnahme-Nachweis.
        $betreuerin = User::create(['name' => 'Petra Sommer', 'email' => 'betreuung@opcare.local', 'password' => Hash::make('password'), 'tenant_id' => $tenant->id]);
        $betreuerin->assignRole('betreuungskraft');
        $aktive = Resident::query()->where('tenant_id', $tenant->id)->where('status', 'aktiv')->get();
        $singkreis = Betreuungsangebot::create(['datum' => now()->toDateString(), 'beginn' => '10:00', 'dauer_minuten' => 45, 'art' => BetreuungsArt::Musik, 'typ' => BetreuungsTyp::Gruppe, 'titel' => 'Singkreis im Aufenthaltsraum', 'leitung_id' => $betreuerin->id]);
        $gedaechtnis = Betreuungsangebot::create(['datum' => now()->toDateString(), 'beginn' => '15:00', 'dauer_minuten' => 30, 'art' => BetreuungsArt::Gedaechtnistraining, 'typ' => BetreuungsTyp::Gruppe, 'titel' => 'Gedächtnistraining', 'leitung_id' => $betreuerin->id]);
        foreach ($aktive->take(3) as $bewohner) {
            $singkreis->teilnahmen()->create(['resident_id' => $bewohner->id]);
        }
        foreach ($aktive->take(2) as $bewohner) {
            $gedaechtnis->teilnahmen()->create(['resident_id' => $bewohner->id]);
        }

        // Buchhaltung & Warenwirtschaft: Buchhalter:in, Standardkontenrahmen, Lagerartikel je Abteilung,
        // ein gebuchter Wareneingang + Verbrauch (verknüpft Lager → Aufwandskonto der Abteilung).
        $buchhalterin = User::create(['name' => 'Anke Roth', 'email' => 'buchhaltung@opcare.local', 'password' => Hash::make('password'), 'tenant_id' => $tenant->id]);
        $buchhalterin->assignRole('buchhaltung');
        AccountingDefaults::ensureFor($tenant->id);
        $mehl = Artikel::create(['tenant_id' => $tenant->id, 'name' => 'Weizenmehl Type 405', 'einheit' => 'kg', 'abteilung' => Abteilung::Kueche, 'bestand' => 0, 'mindestbestand' => 20, 'einkaufspreis' => 0.95]);
        $handschuhe = Artikel::create(['tenant_id' => $tenant->id, 'name' => 'Einmalhandschuhe (Box)', 'einheit' => 'Box', 'abteilung' => Abteilung::Pflege, 'bestand' => 0, 'mindestbestand' => 30, 'einkaufspreis' => 4.20]);
        $filter = Artikel::create(['tenant_id' => $tenant->id, 'name' => 'Lüftungsfilter G4', 'einheit' => 'Stück', 'abteilung' => Abteilung::Haustechnik, 'bestand' => 0, 'mindestbestand' => 10, 'einkaufspreis' => 6.50]);
        app(Wareneingang::class)->handle($mehl, 50, 0.95, now()->subDays(3)->toDateString(), 'Großhandel Bergisch');
        app(Wareneingang::class)->handle($handschuhe, 60, 4.20, now()->subDays(3)->toDateString(), 'Medizinbedarf GmbH');
        app(Wareneingang::class)->handle($filter, 5, 6.50, now()->subDays(2)->toDateString(), 'Haustechnik-Service'); // unter Mindestbestand
        app(Warenverbrauch::class)->handle($mehl->fresh(), 12, now()->subDay()->toDateString(), 'Backtag Wohnbereich 1');
        app(Warenverbrauch::class)->handle($handschuhe->fresh(), 35, now()->toDateString(), 'Tagesbedarf Pflege');

        // FIFO sichtbar (§ 256 HGB): zweite Mehl-Lieferung zu höherem Preis → zwei Bewertungsschichten.
        app(Wareneingang::class)->handle($mehl->fresh(), 25, 1.10, now()->toDateString(), 'Großhandel Bergisch (Nachlieferung)');

        // Inventur (§§ 240/241 HGB): Stichtag mit einer erfassten Zähldifferenz (2 kg Mehl-Schwund), abgeschlossen.
        $inventur = app(InventurStarten::class)->handle(now()->toDateString(), Abteilung::Kueche, $buchhalterin->id);
        $mehlPos = $inventur->positionen->firstWhere('artikel_id', $mehl->id);
        $mehlPos?->update(['ist_menge' => (float) $mehlPos->soll_menge - 2, 'gezaehlt_von' => $buchhalterin->id, 'gezaehlt_am' => now()]);
        app(InventurAbschliessen::class)->handle($inventur->fresh(), $buchhalterin->id);

        // Freie Hauptbuchung (GoB/PBV): generischer Buchungssatz, hier Bargeld-Aufnahme von der Bank in die Kasse.
        app(Buchen::class)->handle(
            AccountingDefaults::konto(AccountingDefaults::KASSE)->id,
            AccountingDefaults::konto(AccountingDefaults::BANK)->id,
            300.0, 'Bargeld-Aufnahme für die Handkasse', now()->subDays(2)->toDateString(), 'KB-2026-014');

        // Budget-Setzung (generisch, verzahnt mit der Buchhaltung): Monatslimit auf das Küchen-Aufwandskonto.
        Budget::create([
            'tenant_id' => $tenant->id, 'konto_id' => AccountingDefaults::konto(Abteilung::Kueche->aufwandKonto())->id,
            'limit_betrag' => 200.00, 'warn_prozent' => 80, 'sperre' => false,
        ]);

        // VLM-Beleg-Capture: eine offene Beleg-Analyse mit Einsortierungs-Vorschlag (zur Bestätigung).
        $belegAnalyse = BelegAnalyse::create([
            'tenant_id' => $tenant->id, 'modell' => 'fake', 'konfidenz' => 0.92, 'erstellt_von' => $buchhalterin->id,
            'roh_json' => ['belegtyp' => 'quittung', 'datum' => now()->subDay()->toDateString(), 'betrag' => 24.90, 'lieferant' => 'Demo-Drogeriemarkt'],
        ]);
        $belegAnalyse->vorschlaege()->create([
            'tenant_id' => $tenant->id, 'ziel_typ' => ZielTyp::BuchhaltungBeleg, 'status' => VorschlagStatus::Vorgeschlagen, 'konfidenz' => 0.92,
            'ziel_felder' => ['betrag' => 24.90, 'datum' => now()->subDay()->toDateString(), 'lieferant' => 'Demo-Drogeriemarkt', 'belegtyp' => 'quittung'],
        ]);

        // Team-Energiebarometer (freiwillig, § 26 BDSG): aktueller Wert je Mitarbeitendem, gemischter Hausschnitt.
        foreach ([[$sandra, Energiestufe::Erschoepft], [$tom, Energiestufe::Mittel], [$betreuerin, Energiestufe::Energiegeladen], [$koechin, Energiestufe::Mittel]] as [$ma, $stufe]) {
            Energielevel::create(['tenant_id' => $tenant->id, 'user_id' => $ma->id, 'stufe' => $stufe]);
        }

        // Arbeitsschutz-Nachweise: Demo je Mitarbeiter:in (eine gültige Unterweisung, eine überfällige Vorsorge).
        foreach ([$sandra, $tom] as $ma) {
            Schutznachweis::create(['tenant_id' => $tenant->id, 'user_id' => $ma->id,
                'typ' => NachweisTyp::Unterweisung, 'datum' => now()->subMonths(2)->toDateString()]);
            Schutznachweis::create(['tenant_id' => $tenant->id, 'user_id' => $ma->id,
                'typ' => NachweisTyp::Vorsorge, 'datum' => now()->subMonths(28)->toDateString()]);
        }

        // Betreuungsschlüssel (§ 113c) + ergonomische Schichtregeln: Defaults je Einrichtung anlegen.
        PersonalbemessungDefaults::ensureConfig($tenant->id);
        ScheduleQualityDefaults::ensureFor($tenant->id);

        // Spitzenzeiten (tageszeitliche Bedarfs-Fenster) + ein kurzer Spitzendienst als Stammdatum.
        SpitzenzeitDefaults::ensureFor($tenant->id);
        Shift::firstOrCreate(['name' => 'Spitzendienst Mittag'], ['kind' => ShiftKind::Spitzendienst, 'beginn' => '11:30', 'ende' => '14:00', 'timeslots' => []]);

        // Dokumente & Fotos: Demo-Befund am Bewohner (medizinisch → 10-Jahres-Aufbewahrung § 630f BGB).
        $maria->addMediaFromString("Demo-Arztbrief\nBefund: stabil.\n")
            ->usingFileName('arztbrief-demo.txt')->usingName('Arztbrief (Demo)')
            ->withCustomProperties([
                'kategorie' => 'befund', 'medizinisch' => true,
                'retention_until' => now()->addYears(10)->toDateString(), 'einwilligung_von' => null,
            ])->toMediaCollection('documents');

        // Prävention (§ 5 SGB XI, kassenfinanziert): zwei Programme + Teilnahmen als Verwendungsnachweis.
        $sturz = Praeventionsprogramm::create(['tenant_id' => $tenant->id,
            'handlungsfeld' => Handlungsfeld::Bewegung, 'titel' => 'Sturzpräventions-Gymnastik',
            'frequenz' => 'wöchentlich', 'verantwortlich' => $betreuerin->name]);
        Praeventionsprogramm::create(['tenant_id' => $tenant->id,
            'handlungsfeld' => Handlungsfeld::Kognition, 'titel' => 'Gedächtnistraining-Gruppe',
            'frequenz' => 'wöchentlich', 'verantwortlich' => $betreuerin->name]);
        foreach ($aktive->take(4) as $bewohner) {
            $sturz->teilnahmen()->create(['tenant_id' => $tenant->id, 'resident_id' => $bewohner->id,
                'datum' => now()->subDays(3)->toDateString(), 'dauer_minuten' => 45, 'beobachtung' => 'gute Beteiligung']);
        }

        // Krankmeldung + Tauschbörse: Tom meldet sich für morgen krank → seine Dienste werden als Vertretung offen.
        app(ShiftCoverageService::class)->krankmelden(
            $tom, AbwesenheitTyp::Krank, now()->addDay()->toDateString(), now()->addDays(2)->toDateString(), 'grippaler Infekt', $admin->id);

        // BtM-Nachweis (§ 13 BtMVV): Konto + Zugang + Gabe für eine:n Bewohner:in.
        $btmBewohner = $aktive->first();
        if ($btmBewohner !== null) {
            $btmKonto = BtmKonto::create(['tenant_id' => $tenant->id, 'resident_id' => $btmBewohner->id,
                'substanz' => 'Morphin', 'staerke' => '10 mg/ml', 'einheit' => 'ml', 'arzt_name' => 'Dr. Wagner', 'eroeffnet_am' => now()->subDays(10)->toDateString()]);
            app(BtmBuchen::class)->handle($btmKonto, BtmVorgang::Lieferung, 20, now()->subDays(10)->toDateString(), ['lieferant' => 'St.-Anna-Apotheke', 'arzt_name' => 'Dr. Wagner', 'durchgefuehrt_von' => $admin->id]);
            app(BtmBuchen::class)->handle($btmKonto, BtmVorgang::Gabe, 3, now()->subDays(2)->toDateString(), ['durchgefuehrt_von' => $admin->id]);
        }

        // FEM (§ 1831 BGB): ein gerichtlich genehmigter, befristeter Fall + Überwachungseintrag.
        if ($btmBewohner !== null) {
            $fem = FemFall::create(['tenant_id' => $tenant->id, 'resident_id' => $btmBewohner->id,
                'art' => FemArt::Bettgitter, 'anlass' => 'wiederholte nächtliche Stürze mit Verletzungsgefahr',
                'mildere_mittel' => ['Niederflurbett', 'Sensormatte'], 'mildere_begruendung' => 'Niederflurbett baulich nicht möglich, Sensormatte allein unzureichend',
                'anordnung_pflegekraft' => $admin->id, 'anordnung_arzt' => 'Dr. Wagner', 'anordnung_am' => now()->subDays(20),
                'einwilligungsstatus' => FemEinwilligung::GenehmigungErteilt,
                'aktenzeichen' => 'XVII 4521/26', 'gericht' => 'Amtsgericht Wuppertal', 'beschluss_am' => now()->subDays(18)->toDateString(),
                'gueltig_bis' => now()->addMonths(11)->toDateString()]);
            $fem->protokolle()->create(['tenant_id' => $tenant->id, 'zeitpunkt' => now()->subHours(6), 'typ' => 'kontrolle', 'befund' => 'ruhig, Haut o. B.', 'indikation_gegeben' => true, 'dokumentiert_von' => $admin->id]);
        }

        // Taschengeldkasse (§ 27b SGB XII): Treuhandkonto + Buchungen + Budget mit Warn-/Sperr-Ampel.
        if ($btmBewohner !== null) {
            $treuhand = Treuhandkonto::create(['tenant_id' => $tenant->id, 'resident_id' => $btmBewohner->id,
                'iban' => 'DE02120300000000202051', 'eroeffnet_am' => now()->subMonths(3)->toDateString()]);
            app(TreuhandBuchen::class)->handle($treuhand, TreuhandVorgang::Einzahlung, 150.00, now()->subMonths(3)->toDateString(), ['zweck' => 'Einzahlung Angehörige', 'erfasst_von' => $admin->id]);
            app(TreuhandBuchen::class)->handle($treuhand, TreuhandVorgang::Auszahlung, 18.50, now()->subDays(20)->toDateString(), ['zweck' => 'Friseurbesuch', 'kategorie' => BarbetragKategorie::Friseur, 'beleg_nr' => 'Q-1042', 'erfasst_von' => $admin->id]);
            app(TreuhandBuchen::class)->handle($treuhand, TreuhandVorgang::Auszahlung, 9.90, now()->subDays(5)->toDateString(), ['zweck' => 'Zeitschriften', 'kategorie' => BarbetragKategorie::Freizeit, 'beleg_nr' => 'Q-1067', 'erfasst_von' => $admin->id]);
            Treuhandbudget::create(['tenant_id' => $tenant->id, 'treuhand_konto_id' => $treuhand->id,
                'kategorie' => BarbetragKategorie::Friseur->value, 'limit_betrag' => 30.00, 'warn_prozent' => 80, 'sperre' => false]);
            Treuhandbudget::create(['tenant_id' => $tenant->id, 'treuhand_konto_id' => $treuhand->id,
                'kategorie' => null, 'limit_betrag' => 120.00, 'warn_prozent' => 90, 'sperre' => true]);
        }

        // Skill-Baum + Berechtigungsmatrix + Delegation + Beauftragten-Register (Demo).
        $komp = KompetenzDefaults::ensureFor($tenant->id)->keyBy('key');
        $taet = TaetigkeitDefaults::ensureFor($tenant->id)->keyBy('key');
        $brollen = BeauftragtenrolleDefaults::ensureFor($tenant->id)->keyBy('key');
        $kompErteilen = function ($user, string $key) use ($tenant, $komp, $admin) {
            $k = $komp[$key];
            MitarbeiterKompetenz::firstOrCreate(
                ['user_id' => $user->id, 'kompetenz_id' => $k->id],
                ['tenant_id' => $tenant->id, 'erworben_am' => now()->subMonths(6)->toDateString(),
                    'gueltig_bis' => $k->gueltigkeit_monate ? now()->subMonths(6)->addMonths($k->gueltigkeit_monate)->toDateString() : null,
                    'verifiziert_von' => $admin->id],
            );
        };
        foreach (['pflegefachkraft', 'wundexperte_icw', 'praxisanleiter'] as $k) {
            $kompErteilen($sandra, $k);
        }
        foreach (['pflegehilfskraft', 'lg1', 'lg2', 'sc_injektion'] as $k) {
            $kompErteilen($tom, $k);
        }
        // Delegation: SC-Injektion an Tom (Hilfskraft) durch den Arzt.
        Delegation::create(['tenant_id' => $tenant->id, 'taetigkeit_id' => $taet['sc_injektion']->id,
            'nehmer_id' => $tom->id, 'anordner_name' => 'Dr. Wagner', 'delegiert_am' => now()->subMonth()->toDateString(),
            'gueltig_bis' => now()->addMonths(11)->toDateString(), 'nachweis_notiz' => 'Spritzenschein + Einweisung']);
        // Beauftragte: Sandra Hygiene, Tom Ersthelfer (übrige Pflicht-Rollen bleiben offen → Lücken-Ampel).
        Beauftragtenbestellung::create(['tenant_id' => $tenant->id, 'beauftragten_rolle_id' => $brollen['hygiene']->id,
            'user_id' => $sandra->id, 'bestellt_am' => now()->subMonths(2)->toDateString(), 'gueltig_bis' => now()->subMonths(2)->addMonths(36)->toDateString()]);
        Beauftragtenbestellung::create(['tenant_id' => $tenant->id, 'beauftragten_rolle_id' => $brollen['ersthelfer']->id,
            'user_id' => $tom->id, 'bestellt_am' => now()->subMonths(20)->toDateString(), 'gueltig_bis' => now()->subMonths(20)->addMonths(24)->toDateString()]);

        // Betriebsärztliche & sicherheitstechnische Betreuung (ASiG/DGUV V2): Begehung überfällig → Ampel.
        $betriebsarzt = Betriebsbetreuung::create(['tenant_id' => $tenant->id, 'art' => BetriebsbetreuungArt::Betriebsarzt,
            'name' => 'Dr. med. Petra Vogt', 'firma' => 'B·A·D Gesundheitsdienst', 'extern' => true, 'telefon' => '0202 555-120',
            'email' => 'vogt@bad-dienst.de', 'bestellt_am' => now()->subYears(3)->toDateString(), 'einsatzzeit_stunden' => 30,
            'letzte_begehung' => now()->subMonths(14)->toDateString(), 'begehung_intervall_monate' => 12]); // Begehung überfällig → rot
        $sifa = Betriebsbetreuung::create(['tenant_id' => $tenant->id, 'art' => BetriebsbetreuungArt::Sifa,
            'name' => 'Markus Renner', 'firma' => 'B·A·D Gesundheitsdienst', 'extern' => true, 'telefon' => '0202 555-121',
            'einsatzzeit_stunden' => 40, 'letzte_begehung' => now()->subMonths(4)->toDateString(), 'begehung_intervall_monate' => 12]);

        // Gremien & Mitwirkung: Heimbeirat (Neuwahl fällig) + Arbeitsschutzausschuss (§ 11 ASiG, vierteljährlich).
        $heimbeirat = Gremium::create(['tenant_id' => $tenant->id, 'typ' => GremiumTyp::Heimbeirat, 'name' => 'Heimbeirat',
            'beschreibung' => 'Vertretung der Bewohnerinnen und Bewohner (HeimmwV, § 10 WBVG).',
            'gewaehlt_am' => now()->subMonths(26)->toDateString(), 'periode_monate' => 24, 'sitzung_intervall_monate' => 3]); // Wahlperiode abgelaufen → rot
        $heimbeirat->mitglieder()->create(['tenant_id' => $tenant->id, 'name' => 'Wilhelm Müller', 'art' => 'bewohner', 'funktion' => 'vorsitz', 'resident_id' => $wilhelm->id]);
        $heimbeirat->mitglieder()->create(['tenant_id' => $tenant->id, 'name' => 'Kurt Petersen', 'art' => 'bewohner', 'funktion' => 'stellvertretung', 'resident_id' => $kurt->id]);
        $heimbeirat->mitglieder()->create(['tenant_id' => $tenant->id, 'name' => 'Sandra Berg', 'art' => 'mitarbeiter', 'funktion' => 'schriftfuehrung', 'user_id' => $sandra->id]);
        $heimbeirat->sitzungen()->create(['tenant_id' => $tenant->id, 'datum' => now()->subMonths(5)->toDateString(), 'thema' => 'Speiseplan & Aktivitäten',
            'protokoll' => 'Wünsche zur Wochenkarte besprochen.', 'beschluesse' => 'Monatlicher Wunschtag wird eingeführt.', 'teilnehmerzahl' => 7, 'protokoll_von' => $sandra->id]);

        $asa = Gremium::create(['tenant_id' => $tenant->id, 'typ' => GremiumTyp::Arbeitsschutzausschuss, 'name' => 'Arbeitsschutzausschuss (ASA)',
            'beschreibung' => 'Quartalssitzung mit Betriebsarzt und Sifa (§ 11 ASiG).', 'sitzung_intervall_monate' => 3]);
        $asa->mitglieder()->create(['tenant_id' => $tenant->id, 'name' => 'Dr. med. Petra Vogt', 'art' => 'betriebsarzt', 'funktion' => 'mitglied']);
        $asa->mitglieder()->create(['tenant_id' => $tenant->id, 'name' => 'Markus Renner', 'art' => 'sifa', 'funktion' => 'mitglied']);
        $asa->mitglieder()->create(['tenant_id' => $tenant->id, 'name' => 'Heimleitung', 'art' => 'leitung', 'funktion' => 'vorsitz', 'user_id' => $admin->id]);
        $asa->sitzungen()->create(['tenant_id' => $tenant->id, 'datum' => now()->subMonths(2)->toDateString(), 'thema' => 'Gefährdungsbeurteilung Pflege',
            'beschluesse' => 'Hebehilfen WB 1 werden beschafft.', 'teilnehmerzahl' => 5, 'protokoll_von' => $admin->id]);

        // Beschwerde-/Gewaltschutz-Management (§ 113 SGB XI): inkl. anonymer Weiterleitung an die Küche.
        $kalt = Beschwerde::create(['tenant_id' => $tenant->id, 'titel' => 'Mittagessen mehrfach zu kalt', 'beschreibung' => 'Das Essen kommt seit zwei Wochen lauwarm auf WB 1 an.',
            'kategorie' => 'beschwerde', 'bereich' => 'kueche', 'quelle' => 'bewohner', 'melder_sichtbarkeit' => 'anonym',
            'eingang_am' => now()->subDays(4)->toDateString(), 'frist' => now()->addDays(3)->toDateString(), 'status' => 'weitergeleitet', 'bearbeiter_user_id' => $sandra->id]);
        $kalt->vorgaenge()->create(['tenant_id' => $tenant->id, 'art' => 'weiterleitung', 'an_bereich' => 'kueche', 'anonym' => true,
            'text' => 'Bitte Temperaturkette prüfen. Melder bleibt anonym.', 'von_user_id' => $sandra->id]);

        Beschwerde::create(['tenant_id' => $tenant->id, 'titel' => 'Verdacht auf grobe Ansprache nachts', 'beschreibung' => 'Angehörige berichtet von ruppigem Umgangston in der Nachtschicht.',
            'kategorie' => 'gewaltvorfall', 'bereich' => 'pflege', 'quelle' => 'angehoerige', 'melder_sichtbarkeit' => 'namentlich', 'melder_name' => 'Tochter von Frau Schneider',
            'eingang_am' => now()->subDay()->toDateString(), 'frist' => now()->addDays(2)->toDateString(), 'status' => 'in_bearbeitung', 'schweregrad' => 'mittel', 'bearbeiter_user_id' => $admin->id]); // ohne Sofortmaßnahme → rot

        Beschwerde::create(['tenant_id' => $tenant->id, 'titel' => 'Lob für die Betreuung', 'beschreibung' => 'Großes Lob an das Betreuungsteam für den Sommerausflug.',
            'kategorie' => 'lob', 'bereich' => 'betreuung', 'quelle' => 'angehoerige', 'melder_sichtbarkeit' => 'namentlich', 'melder_name' => 'Familie Müller',
            'eingang_am' => now()->subDays(10)->toDateString(), 'status' => 'erledigt', 'erledigt_am' => now()->subDays(9)->toDateString(), 'ergebnis' => 'Im Team weitergegeben.']);

        // Rechtliche Vertretung mit Aufgabenkreisen (§§ 1814/1815 BGB) + Portal-Konto + Ereignisse (§ 1821).
        $betreuerUser = User::create(['name' => 'RA Petra Vormund', 'email' => 'betreuer@aprath.local',
            'password' => Hash::make('password'), 'tenant_id' => $tenant->id]);
        $betreuerUser->assignRole('betreuer');

        Custodian::create(['tenant_id' => $tenant->id, 'resident_id' => $wilhelm->id, 'user_id' => $betreuerUser->id,
            'typ' => VertretungTyp::GesetzlicherBetreuer->value, 'name' => 'RA Petra Vormund', 'email' => 'betreuer@aprath.local',
            'beruflich' => true, 'gericht' => 'AG Wuppertal', 'aktenzeichen' => 'XVII 123/25',
            'aufgabenkreise' => [Aufgabenkreis::Gesundheitssorge->value, Aufgabenkreis::Aufenthaltsbestimmung->value, Aufgabenkreis::Vermoegenssorge->value],
            'gueltig_bis' => now()->addYear()->toDateString(), 'bericht_intervall_monate' => 12,
            'letzter_bericht_am' => now()->subMonths(13)->toDateString()]); // Bericht überfällig → rot

        Custodian::create(['tenant_id' => $tenant->id, 'resident_id' => $kurt->id,
            'typ' => VertretungTyp::Vorsorgebevollmaechtigter->value, 'name' => 'Sohn Klaus Petersen', 'kontakt' => '0202 555-12',
            'aufgabenkreise' => [Aufgabenkreis::Gesundheitssorge->value, Aufgabenkreis::Postangelegenheiten->value]]);

        BewohnerEreignis::create(['tenant_id' => $tenant->id, 'resident_id' => $wilhelm->id, 'kategorie' => EreignisKategorie::MdBegutachtung->value,
            'titel' => 'MD-Begutachtung Pflegegrad', 'beschreibung' => 'Höherstufung beantragt; Termin in der Einrichtung.',
            'datum' => now()->subDays(2)->toDateString(), 'status' => 'offen', 'erstellt_von_user_id' => $admin->id]); // offen + vergangen → rot
        BewohnerEreignis::create(['tenant_id' => $tenant->id, 'resident_id' => $kurt->id, 'kategorie' => EreignisKategorie::Posteingang->value,
            'titel' => 'Briefwahlunterlagen Kommunalwahl', 'datum' => now()->subDay()->toDateString(),
            'status' => 'informiert', 'informiert_am' => now()->toDateString(), 'erstellt_von_user_id' => $admin->id]);
        BewohnerEreignis::create(['tenant_id' => $tenant->id, 'resident_id' => $wilhelm->id, 'kategorie' => EreignisKategorie::HeilbehandlungEinwilligung->value,
            'titel' => 'Einwilligung Kataraktoperation', 'datum' => now()->addDays(5)->toDateString(), 'status' => 'offen', 'erstellt_von_user_id' => $admin->id]);

        // Datenschutz-Register (Art. 30/28 DSGVO): Standard-VVT + ein Dienstleister mit gültigem AVV, einer ohne.
        VvtDefaults::ensureFor($tenant->id);
        Verarbeitungstaetigkeit::where('tenant_id', $tenant->id)->whereIn('schluessel', ['pflegedokumentation', 'medikation', 'personalverwaltung'])
            ->update(['geprueft_am' => now()->subMonths(2)->toDateString()]); // einige geprüft → grün, Rest ungeprüft → rot
        $vtDoku = Verarbeitungstaetigkeit::where('tenant_id', $tenant->id)->where('schluessel', 'pflegedokumentation')->first();
        Auftragsverarbeitung::create(['tenant_id' => $tenant->id, 'verarbeitungstaetigkeit_id' => $vtDoku?->id,
            'dienstleister' => 'Rechenzentrum Diakonie eG (Hosting)', 'zweck' => 'Hosting der Pflegesoftware in einem zertifizierten RZ',
            'kategorien_daten' => 'alle in der App verarbeiteten Daten', 'unterauftragnehmer' => true,
            'vertrag_geschlossen_am' => now()->subMonths(8)->toDateString(), 'pruef_intervall_monate' => 24]); // AVV vorhanden → grün
        Auftragsverarbeitung::create(['tenant_id' => $tenant->id, 'dienstleister' => 'Externer Abrechnungsdienst',
            'zweck' => 'Pflegesatz-Abrechnung gegenüber den Kostenträgern', 'kategorien_daten' => 'Stamm- und Abrechnungsdaten',
            'pruef_intervall_monate' => 24]); // kein AVV → rot

        // Hygiene & Infektionsschutz (§ 23 IfSG): freigegebener Hygieneplan + MRE-/Infektions-Surveillance.
        Hygieneplan::create(['tenant_id' => $tenant->id, 'titel' => 'Hausweiter Hygieneplan', 'version' => '2.1',
            'inhalt' => 'Reinigungs-/Desinfektionsplan, Händehygiene, Umgang mit MRE, Ausbruchsmanagement.',
            'freigegeben_von' => $admin->id, 'freigegeben_am' => now()->subMonths(2)->toDateString(), 'revision_intervall_monate' => 12]); // grün
        Hygieneplan::create(['tenant_id' => $tenant->id, 'titel' => 'Hygieneplan Küche (HACCP-Ergänzung)', 'version' => '0.9',
            'revision_intervall_monate' => 12]); // Entwurf → rot

        InfektionsBefund::create(['tenant_id' => $tenant->id, 'resident_id' => $wilhelm->id, 'erreger' => Erreger::Mrsa->value,
            'art' => BefundArt::Besiedlung->value, 'festgestellt_am' => now()->subMonths(1)->toDateString(),
            'massnahmen' => 'Nasale Sanierung läuft; Einzelzimmer, Standardhygiene.', 'erfasst_von_user_id' => $sandra->id]); // aktiv, nicht meldepflichtig → amber
        InfektionsBefund::create(['tenant_id' => $tenant->id, 'resident_id' => $maria->id, 'erreger' => Erreger::Norovirus->value,
            'art' => BefundArt::NosokomialeInfektion->value, 'festgestellt_am' => now()->subDays(2)->toDateString(),
            'massnahmen' => 'Isolation, Kohortierung WB 1.', 'meldepflichtig' => true, 'erfasst_von_user_id' => $sandra->id]); // Meldung offen → rot
        InfektionsBefund::create(['tenant_id' => $tenant->id, 'resident_id' => $kurt->id, 'erreger' => Erreger::Mrgn3->value,
            'art' => BefundArt::Besiedlung->value, 'festgestellt_am' => now()->subMonths(4)->toDateString(),
            'aufgehoben_am' => now()->subMonths(1)->toDateString(), 'erfasst_von_user_id' => $sandra->id]); // aufgehoben → grün

        // Fortbildungsplan (QPR QB6): Pflicht-Matrix mit gemischter Ampel + eine geplante Fortbildung.
        Fortbildung::create(['tenant_id' => $tenant->id, 'user_id' => $sandra->id, 'thema' => FortbildungsThema::Hygiene->value,
            'titel' => 'Händehygiene & MRE (Auffrischung)', 'anbieter' => 'RKI-Fortbildung', 'absolviert_am' => now()->subMonths(3)->toDateString(),
            'umfang_stunden' => 4, 'pflicht' => true, 'intervall_monate' => 12]); // grün
        Fortbildung::create(['tenant_id' => $tenant->id, 'user_id' => $sandra->id, 'thema' => FortbildungsThema::Datenschutz->value,
            'titel' => 'Datenschutz & Schweigepflicht', 'absolviert_am' => now()->subMonths(14)->toDateString(),
            'umfang_stunden' => 2, 'pflicht' => true, 'intervall_monate' => 12]); // überfällig → rot
        Fortbildung::create(['tenant_id' => $tenant->id, 'user_id' => $tom->id, 'thema' => FortbildungsThema::Brandschutzunterweisung->value,
            'titel' => 'Jährliche Brandschutzunterweisung', 'absolviert_am' => now()->subMonths(2)->toDateString(),
            'umfang_stunden' => 2, 'pflicht' => true, 'intervall_monate' => 12]); // grün
        Fortbildung::create(['tenant_id' => $tenant->id, 'user_id' => $tom->id, 'thema' => FortbildungsThema::Reanimation->value,
            'titel' => 'Reanimationstraining (BLS/AED)', 'anbieter' => 'DRK', 'geplant_am' => now()->addWeeks(2)->toDateString(),
            'pflicht' => true, 'intervall_monate' => 12]); // geplant → grau
        Fortbildung::create(['tenant_id' => $tenant->id, 'user_id' => $sandra->id, 'thema' => FortbildungsThema::Demenz->value,
            'titel' => 'Umgang mit herausforderndem Verhalten', 'absolviert_am' => now()->subMonths(5)->toDateString(),
            'umfang_stunden' => 8, 'pflicht' => false]); // fachlich, absolviert → grün

        // Zweites Heim — Haus Birkenhof (2 Bewohner, kein SIS für Minimal-Demo)
        $birkenhof = Tenant::create(['name' => 'Haus Birkenhof', 'slug' => 'birkenhof']);
        app(CurrentTenant::class)->set($birkenhof);
        $this->call(MedicationReferenceSeeder::class);
        $this->call(ShiftSeeder::class);
        $this->call(InstrumentSeeder::class);

        $birkenhofAdmin = User::create([
            'name' => 'Karl Birken',
            'email' => 'admin@birkenhof.local',
            'password' => Hash::make('password'),
            'tenant_id' => $birkenhof->id,
        ]);
        $birkenhofAdmin->assignRole('admin');

        $birkenhofBuilding = Building::create(['name' => 'Haus Birkenhof']);
        $birkenhofFloor = Floor::create(['building_id' => $birkenhofBuilding->id, 'name' => 'Erdgeschoss']);
        $birkenhofStation = Station::create(['floor_id' => $birkenhofFloor->id, 'name' => 'Wohnbereich A']);

        $birkenhofRoom1 = Room::create(['station_id' => $birkenhofStation->id, 'nummer' => '01', 'betten' => 1]);
        Resident::create([
            'room_id' => $birkenhofRoom1->id,
            'name' => 'Gerda Birkenwald',
            'geburtsdatum' => now()->subYears(81)->format('Y-m-d'),
            'geschlecht' => 'w',
            'pflegegrad' => 2,
            'aufnahme_am' => '2024-02-10',
            'status' => 'aktiv',
        ]);

        $birkenhofRoom2 = Room::create(['station_id' => $birkenhofStation->id, 'nummer' => '02', 'betten' => 1]);
        Resident::create([
            'room_id' => $birkenhofRoom2->id,
            'name' => 'Otto Birkenwald',
            'geburtsdatum' => now()->subYears(77)->format('Y-m-d'),
            'geschlecht' => 'm',
            'pflegegrad' => 3,
            'aufnahme_am' => '2023-11-05',
            'status' => 'aktiv',
        ]);
    }

    /** Demo-Bewohner mit 6 SIS-Lebensbereichen (Ressource↔Belastung, Ampel, Ziele, Maßnahmen). */
    private function residents(): array
    {
        $a = fn (string $status, array $res, array $bel, array $ziele, array $mass, string $upd, string $by) => compact('status', 'res', 'bel', 'ziele', 'mass', 'upd', 'by');

        $area = fn (array $d) => [
            'status' => $d['status'], 'ressourcen' => $d['res'], 'belastungen' => $d['bel'],
            'ziele' => $d['ziele'], 'massnahmen' => $d['mass'], 'updated' => $d['upd'], 'by' => $d['by'],
        ];

        return [
            [
                'name' => 'Maria Schneider', 'room' => '12', 'age' => 84, 'pflegegrad' => 3, 'geschlecht' => 'w',
                'aufnahme' => '2023-03-15', 'eingangsfrage' => 'Möchte selbstbestimmt leben und Kontakt zu ihrer Familie halten.',
                'areas' => [
                    'kognition' => $area($a('beobachten', ['Erkennt vertraute Personen sofort', 'Liest gern die Tageszeitung', 'Erzählt klar von früher'], ['Vergisst zunehmend Termine', 'Zeitlich gelegentlich desorientiert'], ['Orientierung im Tagesablauf erhalten'], ['Tagesstruktur sichtbar am Zimmer aushängen', 'Bei Terminen aktiv erinnern'], 'vor 2 Tagen', 'AK')),
                    'mobilitaet' => $area($a('handlung', ['Kann mit Rollator ca. 15 m gehen', 'Steht morgens selbstständig auf'], ['Erhöhte Sturzgefahr', 'Schwindel beim schnellen Aufstehen', 'Knie links schmerzt'], ['Stürze vermeiden', 'Gehstrecke stabil halten'], ['Begleitung bei jedem Gang zur Toilette', 'Rutschfeste Schuhe anziehen', 'Langsam aufstehen lassen'], 'heute, 07:40', 'BM')),
                    'krankheitsbezogen' => $area($a('beobachten', ['Nimmt Medikamente zuverlässig, wenn gereicht', 'Gute Wundheilung'], ['Bluthochdruck', 'Beginnende Druckstelle am Steiß'], ['Blutdruck stabil halten', 'Druckstelle nicht verschlimmern'], ['Blutdruck morgens messen', 'Alle 2 Std. umlagern', 'Haut am Steiß täglich kontrollieren'], 'heute, 06:15', 'BM')),
                    'selbstversorgung' => $area($a('stabil', ['Isst und trinkt selbstständig', 'Wählt Kleidung gern selbst aus', 'Wäscht Gesicht & Hände allein'], ['Braucht Hilfe beim Duschen', 'Trinkt von sich aus zu wenig'], ['Ausreichende Trinkmenge sichern'], ['Getränk in Reichweite stellen', 'Beim Duschen 2× pro Woche begleiten'], 'gestern, 19:20', 'AK')),
                    'soziale_beziehungen' => $area($a('handlung', ['Erzählt gern von ihrer Zeit als Lehrerin', 'Singt im Chor mit, wenn eingeladen'], ['Bekommt kaum noch Besuch', 'Wirkt in letzter Zeit traurig & zurückgezogen'], ['Teilhabe und Kontakt fördern', 'Stimmung stabilisieren'], ['Biografiegespräch anbieten', 'Zum Singkreis am Donnerstag begleiten', 'Tochter zum Besuch anregen'], 'heute, 08:05', 'AK')),
                    'wohnen' => $area($a('stabil', ['Hält ihr Zimmer gern ordentlich', 'Gießt ihre Zimmerpflanzen selbst'], ['Kann Fenster nicht mehr allein öffnen'], ['Selbstbestimmung im Zimmer erhalten'], ['Pflanzen-Gießkanne griffbereit lassen'], 'vor 4 Tagen', 'TR')),
                ],
            ],
            [
                'name' => 'Wilhelm Müller', 'room' => '08', 'age' => 83, 'pflegegrad' => 4, 'geschlecht' => 'm',
                'aufnahme' => '2022-11-02', 'eingangsfrage' => 'Wünscht sich Sicherheit und den Kontakt zu seinem Sohn.',
                'areas' => [
                    'kognition' => $area($a('handlung', ['Erkennt vertraute Pflegekräfte', 'Reagiert positiv auf Musik aus den 50ern'], ['Vergisst regelmäßig die Medikamente', 'Häufig zeitlich & örtlich desorientiert'], ['Sicherheit durch klare Orientierung'], ['Medikamentengabe immer durch Pflegekraft', 'Ruhige, kurze Sätze verwenden'], 'heute, 06:50', 'BM')),
                    'mobilitaet' => $area($a('beobachten', ['Geht kurze Strecken am Rollator', 'Macht bei Bewegungsübungen mit'], ['Unsicherer Gang am Nachmittag', 'Ermüdet schnell'], ['Mobilität erhalten, Sturz vermeiden'], ['Nachmittags Gänge begleiten', 'Pausen einplanen'], 'gestern, 16:10', 'TR')),
                    'krankheitsbezogen' => $area($a('handlung', ['Akzeptiert Verbandswechsel gut'], ['Diabetes Typ 2 — Blutzucker schwankt', 'Chronische Wunde am Unterschenkel'], ['Blutzucker stabilisieren', 'Wunde zur Abheilung bringen'], ['Blutzucker 3× tägl. messen', 'Verbandswechsel nach Plan', 'Wunddoku mit Foto'], 'heute, 07:05', 'BM')),
                    'selbstversorgung' => $area($a('beobachten', ['Isst selbstständig mit angepasstem Besteck'], ['Braucht Hilfe beim An- & Auskleiden', 'Appetit schwankt stark'], ['Selbstständigkeit beim Essen erhalten'], ['Mahlzeiten in ruhiger Umgebung', 'Beim Ankleiden assistieren'], 'gestern, 18:40', 'AK')),
                    'soziale_beziehungen' => $area($a('stabil', ['Telefoniert wöchentlich mit dem Sohn', 'Spielt gern Skat in der Gruppe'], ['Zieht sich bei Schmerzen zurück'], ['Soziale Aktivität halten'], ['Zur Skatrunde am Dienstag einladen'], 'vor 3 Tagen', 'TR')),
                    'wohnen' => $area($a('stabil', ['Räumt sein Werkzeug-Regal gern selbst'], [], ['Vertraute Umgebung bewahren'], ['Persönliche Gegenstände an festem Platz lassen'], 'vor 6 Tagen', 'TR')),
                ],
            ],
            [
                'name' => 'Hannelore Back', 'room' => '15', 'age' => 79, 'pflegegrad' => 2, 'geschlecht' => 'w',
                'aufnahme' => '2024-01-20', 'eingangsfrage' => 'Möchte gesellig bleiben und ihre Selbstständigkeit erhalten.',
                'areas' => [
                    'kognition' => $area($a('stabil', ['Geistig wach & orientiert'], ['Vergisst gelegentlich Namen'], ['Orientierung erhalten'], ['Keine besonderen Maßnahmen nötig'], 'vor 5 Tagen', 'AK')),
                    'mobilitaet' => $area($a('stabil', ['Geht ohne Hilfsmittel sicher'], [], ['Mobilität erhalten'], ['Zur Gymnastikgruppe motivieren'], 'vor 5 Tagen', 'AK')),
                    'krankheitsbezogen' => $area($a('beobachten', ['Nimmt Medikamente selbst'], ['Beginnende Arthrose in den Händen'], ['Schmerzen lindern'], ['Schmerzverlauf beobachten'], 'gestern, 14:00', 'TR')),
                    'selbstversorgung' => $area($a('stabil', ['Vollständig selbstständig'], [], ['Selbstständigkeit erhalten'], ['—'], 'vor 5 Tagen', 'AK')),
                    'soziale_beziehungen' => $area($a('stabil', ['Sehr gut vernetzt im Haus', 'Regelmäßig Besuch'], [], ['Teilhabe fördern'], ['Kaffeekränzchen weiter ermöglichen'], 'vor 2 Tagen', 'AK')),
                    'wohnen' => $area($a('stabil', ['Versorgt ihre Blumen selbst'], [], ['Selbstbestimmung erhalten'], ['—'], 'vor 7 Tagen', 'TR')),
                ],
            ],
            [
                'name' => 'Kurt Petersen', 'room' => '03', 'age' => 88, 'pflegegrad' => 5, 'geschlecht' => 'm',
                'aufnahme' => '2021-08-11', 'eingangsfrage' => 'Braucht würdevolle, ruhige Versorgung und die Nähe seiner Tochter.',
                'areas' => [
                    'kognition' => $area($a('beobachten', ['Versteht einfache Aufforderungen'], ['Spricht nur noch wenig'], ['Kommunikation ermöglichen'], ['Mit Gesten & Bildern arbeiten'], 'heute, 07:30', 'BM')),
                    'mobilitaet' => $area($a('handlung', ['Kann im Bett mithelfen beim Drehen'], ['Bettlägerig', 'Kann nicht allein sitzen'], ['Kontrakturen vermeiden'], ['Alle 2 Std. umlagern', 'Bewegungsübungen passiv'], 'heute, 08:00', 'BM')),
                    'krankheitsbezogen' => $area($a('handlung', ['Toleriert Pflege gut'], ['Hohes Dekubitusrisiko', 'Schluckstörung'], ['Haut intakt halten', 'Aspiration vermeiden'], ['Hautkontrolle alle 4 Std.', 'Andicken der Getränke', 'Oberkörper beim Essen hoch'], 'heute, 08:10', 'BM')),
                    'selbstversorgung' => $area($a('handlung', ['Öffnet den Mund bei vertrauter Stimme'], ['Vollständig auf Hilfe angewiesen'], ['Würdevolle Versorgung sichern'], ['Essen reichen, viel Zeit lassen', 'Mundpflege 3× tägl.'], 'gestern, 20:00', 'AK')),
                    'soziale_beziehungen' => $area($a('stabil', ['Freut sich sichtbar über die Tochter'], ['Wenig Eigeninitiative für Kontakt'], ['Nähe & Zuwendung geben'], ['Tägl. Besuch der Tochter unterstützen'], 'gestern, 17:30', 'AK')),
                    'wohnen' => $area($a('stabil', ['Mag seine vertraute Umgebung'], [], ['Vertraute, ruhige Umgebung'], ['Lieblingsmusik leise spielen'], 'vor 8 Tagen', 'TR')),
                ],
            ],
            [
                'name' => 'Ingrid Faber', 'room' => '19', 'age' => 76, 'pflegegrad' => 2, 'geschlecht' => 'w',
                'aufnahme' => '2024-05-06', 'eingangsfrage' => 'Schätzt Ordnung, feste Zeiten und Entscheidungen nach ihren Wünschen.',
                'areas' => [
                    'kognition' => $area($a('stabil', ['Voll orientiert, sehr strukturiert'], [], ['Selbstbestimmung erhalten'], ['Feste Abläufe respektieren'], 'vor 4 Tagen', 'TR')),
                    'mobilitaet' => $area($a('beobachten', ['Geht mit Gehstock sicher'], ['Gangunsicher bei Dunkelheit'], ['Sturz nachts vermeiden'], ['Nachtlicht installieren'], 'gestern, 21:00', 'AK')),
                    'krankheitsbezogen' => $area($a('stabil', ['Medikamente selbstständig'], ['Leichte Sehschwäche'], ['Sehhilfe sicherstellen'], ['Brille griffbereit'], 'vor 3 Tagen', 'TR')),
                    'selbstversorgung' => $area($a('stabil', ['Vollständig selbstständig'], [], ['Selbstständigkeit erhalten'], ['—'], 'vor 4 Tagen', 'TR')),
                    'soziale_beziehungen' => $area($a('beobachten', ['Pflegt wenige, aber feste Kontakte'], ['Lehnt Gruppenangebote eher ab'], ['Kontakt nach ihren Wünschen'], ['Einzelgespräche statt Gruppe anbieten'], 'gestern, 15:30', 'AK')),
                    'wohnen' => $area($a('stabil', ['Hält alles selbst in Ordnung'], [], ['Autonomie erhalten'], ['—'], 'vor 6 Tagen', 'TR')),
                ],
            ],
        ];
    }
}
