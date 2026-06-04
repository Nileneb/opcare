<?php

namespace App\Domains\Speech\Contracts;

interface AudioTranscriber
{
    /** Wandelt eine Audiodatei (Pfad auf der 'local'-Disk) in Rohtext. */
    public function transcribe(string $absolutePath): string;
}
