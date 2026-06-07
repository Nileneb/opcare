<?php

namespace App\Domains\Capture\Providers;

use App\Domains\Capture\Contracts\BelegVlmAnalyzer;
use App\Domains\Capture\Contracts\LieferscheinVlmAnalyzer;
use App\Domains\Capture\Services\OllamaBelegAnalyzer;
use App\Domains\Capture\Services\OllamaLieferscheinAnalyzer;
use App\Domains\Capture\Testing\FakeBelegAnalyzer;
use App\Domains\Capture\Testing\FakeLieferscheinAnalyzer;
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

        $this->app->bind(
            LieferscheinVlmAnalyzer::class,
            config('speech.fake') ? FakeLieferscheinAnalyzer::class : OllamaLieferscheinAnalyzer::class,
        );
    }
}
