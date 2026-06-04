<?php

namespace App\Domains\Speech\Providers;

use App\Domains\Speech\Contracts\AudioTranscriber;
use App\Domains\Speech\Contracts\SisStructurer;
use App\Domains\Speech\Services\OllamaStructurer;
use App\Domains\Speech\Services\WhisperTranscriber;
use Illuminate\Support\ServiceProvider;

class SpeechServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AudioTranscriber::class, WhisperTranscriber::class);
        $this->app->bind(SisStructurer::class, OllamaStructurer::class);
    }
}
