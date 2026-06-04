<?php

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Facility;
use App\Livewire\Overview;
use App\Livewire\Pflegeplanung;
use App\Livewire\Profile;
use App\Livewire\Residents;
use App\Livewire\ResidentShow;
use App\Livewire\Speech;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

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
    Route::get('/admin/einrichtungen', \App\Livewire\Admin\Tenants::class)->name('admin.tenants');
    Route::get('/admin/benutzer', \App\Livewire\Admin\Users::class)->name('admin.users');

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
