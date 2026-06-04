<?php

namespace App\Domains\Speech\Testing;

use App\Domains\Speech\Contracts\AudioTranscriber;

class FakeAudioTranscriber implements AudioTranscriber
{
    public function __construct(private string $text = 'Frau M. geht heute sicher am Rollator.') {}

    public function transcribe(string $absolutePath): string
    {
        return $this->text;
    }
}
