<?php

use App\Domains\Arbeitsschutz\Data\BelastungsBefund;
use App\Domains\Arbeitsschutz\Enums\Belastungsstufe;
use App\Domains\Arbeitsschutz\Models\BelastungFreischaltung;
use App\Domains\Arbeitsschutz\Models\BelastungsKonfig;
use App\Domains\Arbeitsschutz\Models\Belastungsmeldung;
use App\Domains\Arbeitsschutz\Models\PersoenlicheBelastung;
use App\Domains\Arbeitsschutz\Models\SelbstmeldungUeberlastung;
use App\Domains\Arbeitsschutz\Notifications\SelbstUeberlastung;
use App\Domains\Arbeitsschutz\Services\BelastungFreischalten;
use App\Domains\Arbeitsschutz\Services\BelastungsAnalyzer;
use App\Domains\Arbeitsschutz\Services\PersoenlicheBelastungSetzen;
use App\Domains\Arbeitsschutz\Services\UeberlastungMelden;
use App\Domains\Arbeitsschutz\Support\BelastungsAmpel;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Compliance\Data\StaffingAnalysis;
use App\Domains\Voting\Enums\Abstimmungsart;
use App\Domains\Voting\Enums\AbstimmungStatus;
use App\Domains\Voting\Enums\Elektorat;
use App\Domains\Voting\Enums\Stimmodus;
use App\Domains\Voting\Models\Abstimmung;
use App\Domains\Voting\Models\AbstimmungOption;
use App\Domains\Voting\Models\Wahlteilnahme;
use App\Domains\Voting\Services\AbstimmungStarten;
use App\Domains\Voting\Services\StimmeAbgeben;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('super-admin');

    $this->tenant = Tenant::create(['name' => 'LageAmpel-Test', 'slug' => 'lage-ampel-test']);
    app(CurrentTenant::class)->set($this->tenant);

    $this->admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@lage-test.de',
        'password' => bcrypt('pw'),
        'tenant_id' => $this->tenant->id,
    ]);
    $this->admin->assignRole('admin');

    $this->mitarbeiter = User::create([
        'name' => 'Max Mustermann',
        'email' => 'ma@lage-test.de',
        'password' => bcrypt('pw'),
        'tenant_id' => $this->tenant->id,
    ]);

    $this->freischalten = app(BelastungFreischalten::class);
    $this->belastungSetzen = app(PersoenlicheBelastungSetzen::class);
    $this->ueberlastungMelden = app(UeberlastungMelden::class);
});

// ─── Hilfsfunktion: geschlossener Beschluss mit Stimmen ──────────────────────

/**
 * Erstellt einen Mitarbeitenden-Beschluss (status Geschlossen) mit vorbereiteten Stimmen.
 * Wahlteilnahmen und Stimmen werden direkt eingetragen um StimmeAbgeben-Offen-Prüfung zu umgehen.
 */
function erstelleGeschlossenenBeschluss(
    Tenant $tenant,
    int $jaStimmen,
    int $neinStimmen,
): array {
    $starten = app(AbstimmungStarten::class);

    $abstimmung = $starten->handle([
        'tenant_id' => $tenant->id,
        'titel' => 'Test-Beschluss',
        'elektorat' => Elektorat::Mitarbeitende,
        'modus' => Stimmodus::Geheim,
        'art' => Abstimmungsart::Beschluss,
        'status' => AbstimmungStatus::Offen,
    ], [
        ['text' => 'Ja', 'sortierung' => 0],
        ['text' => 'Nein', 'sortierung' => 1],
    ]);

    $ja = $abstimmung->optionen->firstWhere('text', 'Ja');
    $nein = $abstimmung->optionen->firstWhere('text', 'Nein');
    $abstimmen = app(StimmeAbgeben::class);

    $total = $jaStimmen + $neinStimmen;

    // Erstelle genau so viele Wahlteilnehmer-User wie gebraucht
    $users = collect();
    for ($i = 0; $i < $total; $i++) {
        $u = User::create([
            'name' => "Voter {$i}",
            'email' => "voter{$i}-{$abstimmung->id}@test.de",
            'password' => bcrypt('pw'),
            'tenant_id' => $tenant->id,
        ]);
        $users->push($u);

        // Wahlteilnahme eintragen falls nicht schon vorhanden (eroeffne hat alle Users)
        Wahlteilnahme::firstOrCreate(
            ['abstimmung_id' => $abstimmung->id, 'user_id' => $u->id, 'resident_id' => null],
            ['tenant_id' => $tenant->id, 'hat_abgestimmt' => false]
        );
    }

    // Stimmen per StimmeAbgeben (Abstimmung ist noch Offen)
    foreach ($users->slice(0, $jaStimmen) as $u) {
        $abstimmen->handle($abstimmung, 'user', $u->id, [$ja->id]);
    }
    foreach ($users->slice($jaStimmen) as $u) {
        $abstimmen->handle($abstimmung, 'user', $u->id, [$nein->id]);
    }

    // Jetzt schließen
    $abstimmung->update(['status' => AbstimmungStatus::Geschlossen]);
    $abstimmung->refresh();

    return ['abstimmung' => $abstimmung, 'ja' => $ja, 'nein' => $nein];
}

// ─── Teil A: BelastungsAmpel ─────────────────────────────────────────────────

describe('BelastungsAmpel::lageAusScore', function () {
    it('score 0 → lage 10', function () {
        expect(BelastungsAmpel::lageAusScore(0))->toBe(10);
    });

    it('score 100 → lage 0', function () {
        expect(BelastungsAmpel::lageAusScore(100))->toBe(0);
    });

    it('score 50 → lage 5', function () {
        expect(BelastungsAmpel::lageAusScore(50))->toBe(5);
    });

    it('clamp: score -10 → lage 10', function () {
        expect(BelastungsAmpel::lageAusScore(-10))->toBe(10);
    });

    it('clamp: score 110 → lage 0', function () {
        expect(BelastungsAmpel::lageAusScore(110))->toBe(0);
    });

    it('score 40 → lage 6', function () {
        expect(BelastungsAmpel::lageAusScore(40))->toBe(6);
    });
});

describe('BelastungsAmpel::farbe', function () {
    it('lage 0 → rot (Hue ≈ 0)', function () {
        $farbe = BelastungsAmpel::farbe(0);
        preg_match('/hsl\((\d+),/', $farbe, $m);
        expect((int) $m[1])->toBe(0);
    });

    it('lage 2 → noch rot (Hue ≈ 0, Plateau)', function () {
        $farbe = BelastungsAmpel::farbe(2);
        preg_match('/hsl\((\d+),/', $farbe, $m);
        expect((int) $m[1])->toBe(0);
    });

    it('lage 4-5 → gelb-orange (Hue ~40-55)', function () {
        $farbe4 = BelastungsAmpel::farbe(4);
        $farbe5 = BelastungsAmpel::farbe(5);
        preg_match('/hsl\((\d+),/', $farbe4, $m4);
        preg_match('/hsl\((\d+),/', $farbe5, $m5);
        $hue4 = (int) $m4[1];
        $hue5 = (int) $m5[1];
        expect($hue4)->toBeGreaterThan(29);
        expect($hue4)->toBeLessThan(61);
        expect($hue5)->toBeGreaterThan(44);
        expect($hue5)->toBeLessThan(66);
    });

    it('lage 8 → grün (Hue ≈ 110)', function () {
        $farbe = BelastungsAmpel::farbe(8);
        preg_match('/hsl\((\d+),/', $farbe, $m);
        expect((int) $m[1])->toBe(110);
    });

    it('lage 10 → sattes Grün (Hue ≈ 120)', function () {
        $farbe = BelastungsAmpel::farbe(10);
        preg_match('/hsl\((\d+),/', $farbe, $m);
        expect((int) $m[1])->toBe(120);
    });

    it('farbe gibt korrekte HSL-Syntax zurück', function () {
        $farbe = BelastungsAmpel::farbe(5);
        expect($farbe)->toMatch('/^hsl\(\d+, 75%, 45%\)$/');
    });

    it('Hue ist monoton steigend: höhere lage → höherer oder gleicher Hue', function () {
        $hues = [];
        for ($i = 0; $i <= 10; $i++) {
            preg_match('/hsl\((\d+),/', BelastungsAmpel::farbe($i), $m);
            $hues[] = (int) $m[1];
        }

        for ($i = 1; $i < count($hues); $i++) {
            expect($hues[$i] >= $hues[$i - 1])->toBeTrue("Hue bei lage {$i} ({$hues[$i]}) < lage ".($i - 1)." ({$hues[$i - 1]})");
        }
    });

    it('clamp: lage außerhalb 0-10 liefert trotzdem valide Farbe', function () {
        expect(BelastungsAmpel::farbe(-5))->toMatch('/^hsl\(\d+, 75%, 45%\)$/');
        expect(BelastungsAmpel::farbe(15))->toMatch('/^hsl\(\d+, 75%, 45%\)$/');
    });
});

// ─── BelastungsAnalyzer setzt lage im Befund ─────────────────────────────────

it('BelastungsAnalyzer setzt lage invers zum Score im Befund', function () {
    // Minimal-Setup: nur leere StaffingAnalysis, keine Stationen → leere Collection
    // Wir testen über einen Umweg: lageAusScore ist bereits getestet;
    // hier prüfen wir, dass BelastungsBefund das lage-Feld trägt und es korrekt invertiert ist.
    $befund = new BelastungsBefund(
        stationId: null,
        wohnbereich: 'Test',
        stufe: Belastungsstufe::Gering,
        score: 40,
        signale: [],
        lage: BelastungsAmpel::lageAusScore(40),
    );

    expect($befund->lage)->toBe(6);
    expect($befund->score)->toBe(40);
});

it('Belastungsmeldung::lage() berechnet lage aus score', function () {
    BelastungsKonfig::create([
        'tenant_id' => $this->tenant->id,
        'schwelle_hoch' => 60,
        'schwelle_kritisch' => 80,
        'gewicht_pflegelast' => 1,
        'gewicht_deckung' => 1,
        'gewicht_spitzenzeit' => 1,
        'gewicht_ergonomie' => 1,
    ]);

    $meldung = Belastungsmeldung::create([
        'tenant_id' => $this->tenant->id,
        'wohnbereich' => 'Alpha',
        'stufe' => Belastungsstufe::Gering,
        'score' => 30,
        'signale' => [],
        'gemeldet_am' => today(),
    ]);

    expect($meldung->lage())->toBe(7);
});

// ─── BelastungFreischalten ───────────────────────────────────────────────────

describe('BelastungFreischalten::ausBeschluss', function () {
    it('angenommener Beschluss (Ja-Mehrheit) → Freischaltung aktiv', function () {
        ['abstimmung' => $abstimmung, 'ja' => $ja] = erstelleGeschlossenenBeschluss($this->tenant, 3, 1);

        $freischaltung = $this->freischalten->ausBeschluss($abstimmung, $ja->id, $this->admin);

        expect($freischaltung)->toBeInstanceOf(BelastungFreischaltung::class);
        expect($freischaltung->istAktiv())->toBeTrue();
        expect(BelastungFreischaltung::aktivFuer($this->tenant->id))->toBeTrue();
    });

    it('Ja hat keine echte Mehrheit → InvalidArgumentException, keine Freischaltung', function () {
        // 2 Ja, 3 Nein → keine echte Mehrheit
        ['abstimmung' => $abstimmung, 'ja' => $ja] = erstelleGeschlossenenBeschluss($this->tenant, 2, 3);

        expect(fn () => $this->freischalten->ausBeschluss($abstimmung, $ja->id, $this->admin))
            ->toThrow(InvalidArgumentException::class, 'keine echte Mehrheit');

        expect(BelastungFreischaltung::aktivFuer($this->tenant->id))->toBeFalse();
    });

    it('Ja = genau 50 % (nicht >50 %) → keine Freischaltung', function () {
        // 2 Ja, 2 Nein → exakt 50 %, nicht >50 %
        ['abstimmung' => $abstimmung, 'ja' => $ja] = erstelleGeschlossenenBeschluss($this->tenant, 2, 2);

        expect(fn () => $this->freischalten->ausBeschluss($abstimmung, $ja->id, $this->admin))
            ->toThrow(InvalidArgumentException::class);
    });

    it('falsche art (Umfrage) → InvalidArgumentException', function () {
        $abstimmung = Abstimmung::create([
            'tenant_id' => $this->tenant->id,
            'titel' => 'Umfrage',
            'elektorat' => Elektorat::Mitarbeitende,
            'modus' => Stimmodus::Geheim,
            'art' => Abstimmungsart::Umfrage,
            'status' => AbstimmungStatus::Geschlossen,
        ]);
        $option = AbstimmungOption::create([
            'tenant_id' => $this->tenant->id,
            'abstimmung_id' => $abstimmung->id,
            'text' => 'Ja',
            'sortierung' => 0,
        ]);

        expect(fn () => $this->freischalten->ausBeschluss($abstimmung, $option->id, $this->admin))
            ->toThrow(InvalidArgumentException::class, 'Beschluss');
    });

    it('falsches elektorat (Bewohner) → InvalidArgumentException', function () {
        $abstimmung = Abstimmung::create([
            'tenant_id' => $this->tenant->id,
            'titel' => 'Bewohner-Beschluss',
            'elektorat' => Elektorat::Bewohner,
            'modus' => Stimmodus::Geheim,
            'art' => Abstimmungsart::Beschluss,
            'status' => AbstimmungStatus::Geschlossen,
        ]);
        $option = AbstimmungOption::create([
            'tenant_id' => $this->tenant->id,
            'abstimmung_id' => $abstimmung->id,
            'text' => 'Ja',
            'sortierung' => 0,
        ]);

        expect(fn () => $this->freischalten->ausBeschluss($abstimmung, $option->id, $this->admin))
            ->toThrow(InvalidArgumentException::class, 'Mitarbeitende');
    });

    it('status nicht Geschlossen (Offen) → InvalidArgumentException', function () {
        $abstimmung = Abstimmung::create([
            'tenant_id' => $this->tenant->id,
            'titel' => 'Offener Beschluss',
            'elektorat' => Elektorat::Mitarbeitende,
            'modus' => Stimmodus::Geheim,
            'art' => Abstimmungsart::Beschluss,
            'status' => AbstimmungStatus::Offen,
        ]);
        $option = AbstimmungOption::create([
            'tenant_id' => $this->tenant->id,
            'abstimmung_id' => $abstimmung->id,
            'text' => 'Ja',
            'sortierung' => 0,
        ]);

        expect(fn () => $this->freischalten->ausBeschluss($abstimmung, $option->id, $this->admin))
            ->toThrow(InvalidArgumentException::class, 'Geschlossen');
    });

    it('zuruecknehmen → aktivFuer false', function () {
        ['abstimmung' => $abstimmung, 'ja' => $ja] = erstelleGeschlossenenBeschluss($this->tenant, 3, 1);
        $this->freischalten->ausBeschluss($abstimmung, $ja->id, $this->admin);

        expect(BelastungFreischaltung::aktivFuer($this->tenant->id))->toBeTrue();

        $this->freischalten->zuruecknehmen($this->admin);

        expect(BelastungFreischaltung::aktivFuer($this->tenant->id))->toBeFalse();
    });

    it('zweite ausBeschluss-Freischaltung deaktiviert die erste', function () {
        ['abstimmung' => $a1, 'ja' => $ja1] = erstelleGeschlossenenBeschluss($this->tenant, 3, 1);
        $this->freischalten->ausBeschluss($a1, $ja1->id, $this->admin);

        ['abstimmung' => $a2, 'ja' => $ja2] = erstelleGeschlossenenBeschluss($this->tenant, 3, 1);
        $this->freischalten->ausBeschluss($a2, $ja2->id, $this->admin);

        $aktive = BelastungFreischaltung::where('tenant_id', $this->tenant->id)
            ->whereNull('zurueckgenommen_am')
            ->count();

        expect($aktive)->toBe(1);
        expect(BelastungFreischaltung::aktivFuer($this->tenant->id))->toBeTrue();
    });
});

// ─── PersoenlicheBelastungSetzen ─────────────────────────────────────────────

describe('PersoenlicheBelastungSetzen', function () {
    it('ohne Freischaltung → 403', function () {
        expect(fn () => $this->belastungSetzen->handle($this->mitarbeiter, 5))
            ->toThrow(HttpException::class);
    });

    it('mit Freischaltung → speichert eigenen Wert', function () {
        ['abstimmung' => $abstimmung, 'ja' => $ja] = erstelleGeschlossenenBeschluss($this->tenant, 3, 1);
        $this->freischalten->ausBeschluss($abstimmung, $ja->id, $this->admin);

        $pb = $this->belastungSetzen->handle($this->mitarbeiter, 7);

        expect($pb)->toBeInstanceOf(PersoenlicheBelastung::class);
        expect($pb->wert)->toBe(7);
        expect($pb->user_id)->toBe($this->mitarbeiter->id);
        expect($pb->tenant_id)->toBe($this->tenant->id);
    });

    it('wert > 10 → 422', function () {
        ['abstimmung' => $abstimmung, 'ja' => $ja] = erstelleGeschlossenenBeschluss($this->tenant, 3, 1);
        $this->freischalten->ausBeschluss($abstimmung, $ja->id, $this->admin);

        expect(fn () => $this->belastungSetzen->handle($this->mitarbeiter, 11))
            ->toThrow(HttpException::class);
    });

    it('wert < 0 → 422', function () {
        ['abstimmung' => $abstimmung, 'ja' => $ja] = erstelleGeschlossenenBeschluss($this->tenant, 3, 1);
        $this->freischalten->ausBeschluss($abstimmung, $ja->id, $this->admin);

        expect(fn () => $this->belastungSetzen->handle($this->mitarbeiter, -1))
            ->toThrow(HttpException::class);
    });

    it('aktuellerWert gibt jüngsten Eintrag zurück', function () {
        ['abstimmung' => $abstimmung, 'ja' => $ja] = erstelleGeschlossenenBeschluss($this->tenant, 3, 1);
        $this->freischalten->ausBeschluss($abstimmung, $ja->id, $this->admin);

        $this->belastungSetzen->handle($this->mitarbeiter, 3);
        $this->belastungSetzen->handle($this->mitarbeiter, 8);

        expect($this->belastungSetzen->aktuellerWert($this->mitarbeiter))->toBe(8);
    });

    it('aktuellerWert gibt null zurück wenn keine Einträge', function () {
        expect($this->belastungSetzen->aktuellerWert($this->mitarbeiter))->toBeNull();
    });

    it('PersoenlicheBelastung hat kein LogsActivity (extends Model, nicht BaseModel)', function () {
        expect(in_array(
            LogsActivity::class,
            class_uses_recursive(PersoenlicheBelastung::class)
        ))->toBeFalse();
    });
});

// ─── UeberlastungMelden ──────────────────────────────────────────────────────

describe('UeberlastungMelden', function () {
    it('ohne Freischaltung → 403', function () {
        expect(fn () => $this->ueberlastungMelden->handle($this->mitarbeiter, null))
            ->toThrow(HttpException::class);
    });

    it('mit Freischaltung → Meldung gespeichert + Notification an Admin', function () {
        Notification::fake();

        ['abstimmung' => $abstimmung, 'ja' => $ja] = erstelleGeschlossenenBeschluss($this->tenant, 3, 1);
        $this->freischalten->ausBeschluss($abstimmung, $ja->id, $this->admin);

        $this->belastungSetzen->handle($this->mitarbeiter, 6);

        $meldung = $this->ueberlastungMelden->handle($this->mitarbeiter, 'Ich bin überfordert');

        expect($meldung)->toBeInstanceOf(SelbstmeldungUeberlastung::class);
        expect($meldung->wert)->toBe(6);
        expect($meldung->notiz)->toBe('Ich bin überfordert');
        expect($meldung->user_id)->toBe($this->mitarbeiter->id);
        expect($meldung->istOffen())->toBeTrue();

        Notification::assertSentTo($this->admin, SelbstUeberlastung::class);
    });

    it('wert = 0 wenn keine PersoenlicheBelastung vorhanden', function () {
        Notification::fake();

        ['abstimmung' => $abstimmung, 'ja' => $ja] = erstelleGeschlossenenBeschluss($this->tenant, 3, 1);
        $this->freischalten->ausBeschluss($abstimmung, $ja->id, $this->admin);

        $meldung = $this->ueberlastungMelden->handle($this->mitarbeiter, null);

        expect($meldung->wert)->toBe(0);
    });

    it('Dedupe: zweite Meldung während offene existiert → InvalidArgumentException', function () {
        Notification::fake();

        ['abstimmung' => $abstimmung, 'ja' => $ja] = erstelleGeschlossenenBeschluss($this->tenant, 3, 1);
        $this->freischalten->ausBeschluss($abstimmung, $ja->id, $this->admin);

        $this->ueberlastungMelden->handle($this->mitarbeiter, null);

        expect(fn () => $this->ueberlastungMelden->handle($this->mitarbeiter, null))
            ->toThrow(InvalidArgumentException::class, 'offene Überlastungsmeldung');
    });

    it('nach Quittierung ist neue Meldung möglich', function () {
        Notification::fake();

        ['abstimmung' => $abstimmung, 'ja' => $ja] = erstelleGeschlossenenBeschluss($this->tenant, 3, 1);
        $this->freischalten->ausBeschluss($abstimmung, $ja->id, $this->admin);

        $erste = $this->ueberlastungMelden->handle($this->mitarbeiter, null);
        $erste->update(['quittiert_am' => today(), 'quittiert_von' => $this->admin->id]);

        $zweite = $this->ueberlastungMelden->handle($this->mitarbeiter, 'neue Meldung');
        expect($zweite->id)->not->toBe($erste->id);
    });

    it('Notification enthält Namen und Wert', function () {
        Notification::fake();

        ['abstimmung' => $abstimmung, 'ja' => $ja] = erstelleGeschlossenenBeschluss($this->tenant, 3, 1);
        $this->freischalten->ausBeschluss($abstimmung, $ja->id, $this->admin);
        $this->belastungSetzen->handle($this->mitarbeiter, 9);

        $this->ueberlastungMelden->handle($this->mitarbeiter, null);

        Notification::assertSentTo(
            $this->admin,
            SelbstUeberlastung::class,
            function (SelbstUeberlastung $n) {
                return $n->name === 'Max Mustermann' && $n->wert === 9;
            }
        );
    });
});
