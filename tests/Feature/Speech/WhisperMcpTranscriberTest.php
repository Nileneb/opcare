<?php

use App\Domains\Speech\Services\WhisperMcpTranscriber;

it('entfernt Diarization-Labels und verdichtet zu Fließtext', function () {
    $raw = "SPEAKER_00: Frau M. geht am Rollator.\nSPEAKER_01: Heute etwas schwindelig.\n";

    $clean = (new WhisperMcpTranscriber)->stripSpeakerLabels($raw);

    expect($clean)->toBe('Frau M. geht am Rollator. Heute etwas schwindelig.');
});

it('lässt Text ohne Labels unverändert (nur verdichtet)', function () {
    expect((new WhisperMcpTranscriber)->stripSpeakerLabels('  Eingliederung   ins   Heim  '))
        ->toBe('Eingliederung ins Heim');
});
