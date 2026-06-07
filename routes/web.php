<?php

use App\Domains\Fhir\FhirDocumentExporter;
use App\Domains\Masterdata\Models\Resident;
use App\Http\Controllers\MediaDownloadController;
use App\Http\Controllers\SpeechController;
use App\Http\Middleware\RequireTwoFactorEnrollment;
use App\Http\Middleware\RestrictPortalUsers;
use App\Livewire\Accounting\Beschaffung;
use App\Livewire\Accounting\Buchhaltung;
use App\Livewire\Accounting\BetriebsanweisungDruck;
use App\Livewire\Accounting\Gefahrstoffverzeichnis;
use App\Livewire\Accounting\Inventur;
use App\Livewire\Accounting\Pflegehilfsmittel;
use App\Livewire\Accounting\Rueckverfolgung;
use App\Livewire\Accounting\Taschengeldkasse;
use App\Livewire\Admin\Heimrecht;
use App\Livewire\Admin\Tenants;
use App\Livewire\Admin\Users;
use App\Livewire\Assessment\AssessmentDurchfuehren;
use App\Livewire\Assessment\AssessmentVerlauf;
use App\Livewire\Auth\ChallengeTwoFactor;
use App\Livewire\Auth\EnrollTwoFactor;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Capture\Belegerfassung;
use App\Livewire\Capture\Wareneingangerfassung;
use App\Livewire\Catering\Kueche;
use App\Livewire\Compliance\Datenschutz;
use App\Livewire\Facility;
use App\Livewire\Facility\Haustechnik;
use App\Livewire\Facility\Medizinprodukte;
use App\Livewire\Hygiene\Hygiene;
use App\Livewire\Import\Datenimport;
use App\Livewire\Masterdata\Portal;
use App\Livewire\Masterdata\Vertretungen;
use App\Livewire\Medication\BtmNachweis;
use App\Livewire\Medication\Stammdaten;
use App\Livewire\Medication\Stellplan;
use App\Livewire\Medication\VerordnungAnlegen;
use App\Livewire\Medication\Verordnungen;
use App\Livewire\Medication\Vitalwerte;
use App\Livewire\Overview;
use App\Livewire\Personnel\Arbeitsschutz;
use App\Livewire\Personnel\Beauftragtenregister;
use App\Livewire\Personnel\Berechtigungen;
use App\Livewire\Personnel\Energiebarometer;
use App\Livewire\Personnel\Fortbildungsplan;
use App\Livewire\Personnel\Personalakte;
use App\Livewire\Personnel\SkillBaum;
use App\Livewire\Pflegeplanung;
use App\Livewire\Profile;
use App\Livewire\Qdvs\Export as QdvsExport;
use App\Livewire\Quality\Beschwerden;
use App\Livewire\Quality\Controlling;
use App\Livewire\Quality\FemUebersicht;
use App\Livewire\Quality\Gremien;
use App\Livewire\Quality\QmCheckliste;
use App\Livewire\Quality\QualityReport;
use App\Livewire\Residents;
use App\Livewire\ResidentShow;
use App\Livewire\Scheduling\Arbeitsrecht;
use App\Livewire\Scheduling\Dienstplan;
use App\Livewire\Scheduling\Kalender;
use App\Livewire\Scheduling\Spitzenzeiten;
use App\Livewire\Scheduling\Tauschboerse;
use App\Livewire\Scheduling\Wunschdienstplan;
use App\Livewire\Scheduling\Zeiterfassung;
use App\Livewire\SocialCare\Betreuung;
use App\Livewire\SocialCare\Praevention;
use App\Livewire\Speech;
use App\Livewire\Vision\Regalzaehlung;
use App\Livewire\Voting\Abstimmungen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
    // TOTP-Challenge: Benutzer ist nach der Passwortprüfung noch nicht authentifiziert (Session-Hand-off).
    Route::get('/two-factor/challenge', ChallengeTwoFactor::class)->name('two-factor.challenge');
});

Route::middleware(['auth', 'tenant', RequireTwoFactorEnrollment::class, RestrictPortalUsers::class])->group(function () {
    // Pflicht-Enrollment: eingeloggt, aber bis zum Abschluss von der Middleware hierher gehalten.
    Route::get('/two-factor/enroll', EnrollTwoFactor::class)->name('two-factor.enroll');
    Route::get('/', Overview::class)->name('overview');
    Route::get('/bewohner', Residents::class)->name('bewohner');
    Route::get('/bewohner/{resident}', ResidentShow::class)->name('bewohner.show');
    Route::get('/spracherfassung', Speech::class)->name('spracherfassung');
    Route::get('/einrichtung', Facility::class)->name('einrichtung');
    Route::get('/pflegeplanung', Pflegeplanung::class)->name('pflegeplanung');
    Route::get('/betreuung', Betreuung::class)->name('betreuung');
    Route::get('/praevention', Praevention::class)->name('praevention');
    Route::get('/vertretungen', Vertretungen::class)->name('vertretungen');
    Route::get('/mein-bereich', Portal::class)->name('portal');
    Route::get('/profil', Profile::class)->name('profile');
    Route::get('/admin/einrichtungen', Tenants::class)->name('admin.tenants');
    Route::get('/admin/benutzer', Users::class)->name('admin.users');
    Route::get('/admin/mitarbeitende/{user}', Personalakte::class)->name('personnel.akte');
    Route::get('/arbeitsschutz/nachweise', Arbeitsschutz::class)->name('arbeitsschutz.nachweise');
    Route::get('/personal/kompetenzen', SkillBaum::class)->name('personnel.kompetenzen');
    Route::get('/personal/berechtigungen', Berechtigungen::class)->name('personnel.berechtigungen');
    Route::get('/personal/beauftragte', Beauftragtenregister::class)->name('personnel.beauftragte');
    Route::get('/bewohner/{resident}/medikation', Stellplan::class)->name('medikation.stellplan');
    Route::get('/medikation/stamm', Stammdaten::class)->name('medikation.stammdaten');
    Route::get('/medikation/btm', BtmNachweis::class)->name('medikation.btm');
    Route::get('/bewohner/{resident}/verordnung/neu', VerordnungAnlegen::class)->name('medikation.verordnung-anlegen');
    Route::get('/bewohner/{resident}/verordnungen', Verordnungen::class)->name('medikation.verordnungen');
    Route::get('/bewohner/{resident}/vitalwerte', Vitalwerte::class)->name('medikation.vitalwerte');
    Route::get('/controlling', Controlling::class)->name('controlling');
    Route::get('/qualitaet/report', QualityReport::class)->name('quality.report');
    Route::get('/qualitaet/qm-checkliste', QmCheckliste::class)->name('quality.qm');
    Route::get('/qualitaet/fem', FemUebersicht::class)->name('quality.fem');
    Route::get('/qualitaet/beschwerden', Beschwerden::class)->name('quality.beschwerden');
    Route::get('/qualitaet/gremien', Gremien::class)->name('quality.gremien');
    Route::get('/qualitaet/hygiene', Hygiene::class)->name('hygiene');
    Route::get('/datenschutz', Datenschutz::class)->name('datenschutz');
    Route::get('/personal/fortbildung', Fortbildungsplan::class)->name('personnel.fortbildung');
    Route::get('/heimrecht', Heimrecht::class)->name('heimrecht');
    Route::get('/energiebarometer', Energiebarometer::class)->name('energiebarometer');

    // Querschnitts-Sprachfunktionen für jedes Textfeld (inline, synchron).
    Route::post('/speech/transcribe', [SpeechController::class, 'transcribe'])->name('speech.transcribe');
    Route::post('/speech/optimize', [SpeechController::class, 'optimize'])->name('speech.optimize');
    Route::get('/qdvs', QdvsExport::class)->name('qdvs.export');
    Route::get('/dienstplan', Dienstplan::class)->name('dienstplan');
    Route::get('/arbeitsrecht', Arbeitsrecht::class)->name('arbeitsrecht');
    Route::get('/spitzenzeiten', Spitzenzeiten::class)->name('spitzenzeiten');
    Route::get('/kalender', Kalender::class)->name('kalender');
    Route::get('/haustechnik', Haustechnik::class)->name('haustechnik');
    Route::get('/medizinprodukte', Medizinprodukte::class)->name('medizinprodukte');
    Route::get('/kueche', Kueche::class)->name('kueche');
    Route::get('/zeiterfassung', Zeiterfassung::class)->name('zeiterfassung');
    Route::get('/wunschdienstplan', Wunschdienstplan::class)->name('wunschdienstplan');
    Route::get('/tauschboerse', Tauschboerse::class)->name('tauschboerse');
    Route::get('/buchhaltung', Buchhaltung::class)->name('buchhaltung');
    Route::get('/datenimport', Datenimport::class)->name('datenimport');
    Route::get('/beschaffung', Beschaffung::class)->name('beschaffung');
    Route::get('/inventur', Inventur::class)->name('inventur');
    Route::get('/pflegehilfsmittel', Pflegehilfsmittel::class)->name('pflegehilfsmittel');
    Route::get('/gefahrstoffe', Gefahrstoffverzeichnis::class)->name('gefahrstoffe');
    Route::get('/gefahrstoffe/{artikel}/betriebsanweisung', BetriebsanweisungDruck::class)->name('betriebsanweisung-druck');
    Route::get('/beleg-capture', Belegerfassung::class)->name('belegerfassung');
    Route::get('/wareneingang-capture', Wareneingangerfassung::class)->name('wareneingang-capture');
    Route::get('/regalzaehlung', Regalzaehlung::class)->name('regalzaehlung');
    Route::get('/rueckverfolgung', Rueckverfolgung::class)->name('rueckverfolgung');
    Route::get('/taschengeld', Taschengeldkasse::class)->name('taschengeld');
    Route::get('/dokumente/{media}', MediaDownloadController::class)->name('media.download')->middleware('signed');
    Route::get('/bewohner/{resident}/assessment/{instrument}', AssessmentDurchfuehren::class)->name('assessment.durchfuehren');
    Route::get('/bewohner/{resident}/assessments', AssessmentVerlauf::class)->name('assessment.verlauf');
    Route::get('/abstimmungen', Abstimmungen::class)->name('abstimmungen');
    Route::get('/qdvs/{export}/download', function (App\Domains\Qdvs\Models\QdvsExport $export) {
        // WHY(DSGVO Art. 9): pseudonymisierte Gesundheitsdaten — Download nur für Leitung (admin/pflegefachkraft/super-admin).
        abort_unless(
            auth()->user()?->isSuperAdmin() || auth()->user()?->hasAnyRole(['admin', 'pflegefachkraft']),
            403,
        );
        abort_unless($export->pfad && Storage::disk(config('qdvs.disk'))->exists($export->pfad), 404);

        return Storage::disk(config('qdvs.disk'))
            ->download($export->pfad, basename($export->pfad));
    })->name('qdvs.download');

    // FHIR-R4-Document-Bundle (Pflegebericht) — {resident} ist tenant-gescopt (Model-Binding)
    Route::get('/bewohner/{resident}/fhir', function (Resident $resident, FhirDocumentExporter $exporter) {
        // WHY(DSGVO Art. 9): Gesundheitsdaten als Klartext-Dokument — nur Leitungs-/Pflegefachrollen.
        abort_unless(
            auth()->user()?->isSuperAdmin() || auth()->user()?->hasAnyRole(['admin', 'pflegefachkraft']),
            403,
        );

        return response($exporter->toJson($resident), 200, [
            'Content-Type' => 'application/fhir+json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="pflegebericht-'.$resident->id.'.fhir.json"',
        ]);
    })->name('fhir.export');

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
