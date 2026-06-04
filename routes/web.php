<?php

use App\Http\Controllers\SpeechController;
use App\Livewire\Admin\Tenants;
use App\Livewire\Admin\Users;
use App\Livewire\Assessment\AssessmentDurchfuehren;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Facility;
use App\Livewire\Medication\Stammdaten;
use App\Livewire\Medication\Stellplan;
use App\Livewire\Medication\VerordnungAnlegen;
use App\Livewire\Medication\Verordnungen;
use App\Livewire\Medication\Vitalwerte;
use App\Livewire\Overview;
use App\Livewire\Pflegeplanung;
use App\Livewire\Profile;
use App\Livewire\Qdvs\Export as QdvsExport;
use App\Livewire\Quality\Controlling;
use App\Livewire\Quality\QualityReport;
use App\Livewire\Residents;
use App\Livewire\ResidentShow;
use App\Livewire\Scheduling\Dienstplan;
use App\Livewire\Scheduling\Kalender;
use App\Livewire\Speech;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/', Overview::class)->name('overview');
    Route::get('/bewohner', Residents::class)->name('bewohner');
    Route::get('/bewohner/{resident}', ResidentShow::class)->name('bewohner.show');
    Route::get('/spracherfassung', Speech::class)->name('spracherfassung');
    Route::get('/einrichtung', Facility::class)->name('einrichtung');
    Route::get('/pflegeplanung', Pflegeplanung::class)->name('pflegeplanung');
    Route::get('/profil', Profile::class)->name('profile');
    Route::get('/admin/einrichtungen', Tenants::class)->name('admin.tenants');
    Route::get('/admin/benutzer', Users::class)->name('admin.users');
    Route::get('/bewohner/{resident}/medikation', Stellplan::class)->name('medikation.stellplan');
    Route::get('/medikation/stamm', Stammdaten::class)->name('medikation.stammdaten');
    Route::get('/bewohner/{resident}/verordnung/neu', VerordnungAnlegen::class)->name('medikation.verordnung-anlegen');
    Route::get('/bewohner/{resident}/verordnungen', Verordnungen::class)->name('medikation.verordnungen');
    Route::get('/bewohner/{resident}/vitalwerte', Vitalwerte::class)->name('medikation.vitalwerte');
    Route::get('/controlling', Controlling::class)->name('controlling');
    Route::get('/qualitaet/report', QualityReport::class)->name('quality.report');

    // Querschnitts-Sprachfunktionen für jedes Textfeld (inline, synchron).
    Route::post('/speech/transcribe', [SpeechController::class, 'transcribe'])->name('speech.transcribe');
    Route::post('/speech/optimize', [SpeechController::class, 'optimize'])->name('speech.optimize');
    Route::get('/qdvs', QdvsExport::class)->name('qdvs.export');
    Route::get('/dienstplan', Dienstplan::class)->name('dienstplan');
    Route::get('/kalender', Kalender::class)->name('kalender');
    Route::get('/bewohner/{resident}/assessment/{instrument}', AssessmentDurchfuehren::class)->name('assessment.durchfuehren');
    // WHY: assessment.verlauf kommt in Task 8 — temporärer Stub auf Bewohnerprofil.
    Route::get('/bewohner/{resident}/assessments', ResidentShow::class)->name('assessment.verlauf');
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

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
