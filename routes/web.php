<?php

use App\Livewire\Pflegeplanung;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('pflegeplanung'));

Route::get('/pflegeplanung', Pflegeplanung::class)->name('pflegeplanung');
