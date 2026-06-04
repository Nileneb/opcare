<?php

namespace App\Domains\Identity\Database\Seeders;

use App\Domains\CarePlanning\Models\CareMeasure;
use App\Domains\CarePlanning\Models\SisAssessment;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Building;
use App\Domains\Masterdata\Models\Floor;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Masterdata\Models\Room;
use App\Domains\Masterdata\Models\Station;
use App\Domains\Medication\Actions\AddSchedule;
use App\Domains\Medication\Actions\AddStock;
use App\Domains\Medication\Actions\CreatePrescription;
use App\Domains\Medication\Actions\GenerateAdministrations;
use App\Domains\Medication\Data\PrescriptionData;
use App\Domains\Medication\Data\ScheduleData;
use App\Domains\Medication\Data\StockData;
use App\Domains\Medication\Enums\ScheduleFrequency;
use App\Domains\Medication\Models\MedProduct;
use App\Domains\Medication\Models\TradeForm;
use App\Domains\Quality\Enums\EventSeverity;
use App\Domains\Quality\Enums\QualityIndicator;
use App\Domains\Quality\Models\CareEvent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        Role::findOrCreate('super-admin');

        $tenant = Tenant::create(['name' => 'Bergische Diakonie — Wohnbereich Aprath', 'slug' => 'aprath']);
        app(CurrentTenant::class)->set($tenant);

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
        $tablette = TradeForm::create(['name' => 'Tablette', 'einheit' => 'Stk', 'teilbar' => true]);
        $ramipril = MedProduct::create([
            'trade_form_id' => $tablette->id,
            'name' => 'Ramipril 5 mg',
            'wirkstoff' => 'Ramipril',
            'staerke' => '5 mg',
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
            'details' => ['ort' => 'Bad', 'verletzung' => 'Platzwunde Kopf'],
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

        $wilhelm = Resident::query()->where('name', 'Wilhelm Müller')->firstOrFail();
        CareEvent::create([
            'resident_id' => $wilhelm->id,
            'indicator' => QualityIndicator::Sturz,
            'datum' => now()->startOfQuarter()->addDays(12)->toDateString(),
            'severity' => EventSeverity::OhneFolgen,
            'details' => ['ort' => 'Flur', 'verletzung' => 'keine'],
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

        // Zweites Heim — Haus Birkenhof (2 Bewohner, kein SIS für Minimal-Demo)
        $birkenhof = Tenant::create(['name' => 'Haus Birkenhof', 'slug' => 'birkenhof']);
        app(CurrentTenant::class)->set($birkenhof);

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
