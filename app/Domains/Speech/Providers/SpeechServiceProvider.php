<?php

namespace App\Domains\Speech\Providers;

use App\Domains\Speech\Contracts\AudioTranscriber;
use App\Domains\Speech\Contracts\SisStructurer;
use App\Domains\Speech\Contracts\TextOptimizer;
use App\Domains\Speech\Services\OllamaStructurer;
use App\Domains\Speech\Services\OllamaTextOptimizer;
use App\Domains\Speech\Services\WhisperMcpTranscriber;
use App\Domains\Speech\Services\WhisperTranscriber;
use App\Domains\Speech\Testing\FakeAudioTranscriber;
use App\Domains\Speech\Testing\FakeSisStructurer;
use App\Domains\Speech\Testing\FakeTextOptimizer;
use Illuminate\Support\ServiceProvider;

class SpeechServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (config('speech.fake')) {
            $this->app->bind(AudioTranscriber::class, FakeAudioTranscriber::class);
            $this->app->bind(SisStructurer::class, FakeSisStructurer::class);
            $this->app->bind(TextOptimizer::class, FakeTextOptimizer::class);

            return;
        }

        $this->app->bind(AudioTranscriber::class, config('speech.whisper.driver') === 'asr'
            ? WhisperTranscriber::class
            : WhisperMcpTranscriber::class);
        $this->app->bind(SisStructurer::class, OllamaStructurer::class);
        $this->app->bind(TextOptimizer::class, OllamaTextOptimizer::class);
    }
}
