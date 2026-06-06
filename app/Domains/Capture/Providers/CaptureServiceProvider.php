<?php

namespace App\Domains\Capture\Providers;

use App\Domains\Capture\Contracts\BelegVlmAnalyzer;
use App\Domains\Capture\Services\OllamaBelegAnalyzer;
use App\Domains\Capture\Testing\FakeBelegAnalyzer;
use Illuminate\Support\ServiceProvider;

class CaptureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Gleiche Konvention wie das Speech-Modul: SPEECH_FAKE bindet den deterministischen Adapter
        // (dev/test ohne GPU), sonst das Ollama-VLM.
        $this->app->bind(
            BelegVlmAnalyzer::class,
            config('speech.fake') ? FakeBelegAnalyzer::class : OllamaBelegAnalyzer::class,
        );
    }
}
