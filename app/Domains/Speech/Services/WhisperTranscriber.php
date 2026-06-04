<?php

namespace App\Domains\Speech\Services;

use App\Domains\Speech\Contracts\AudioTranscriber;
use Illuminate\Support\Facades\Http;

class WhisperTranscriber implements AudioTranscriber
{
    public function transcribe(string $absolutePath): string
    {
        $response = Http::timeout(config('speech.whisper.timeout'))
            ->attach('audio_file', file_get_contents($absolutePath), basename($absolutePath))
            ->post(rtrim(config('speech.whisper.url'), '/').'/asr', [
                'task' => 'transcribe',
                'language' => 'de',
                'model' => config('speech.whisper.model'),
            ])
            ->throw();

        return trim($response->body());
    }
}
