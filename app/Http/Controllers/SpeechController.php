<?php

namespace App\Http\Controllers;

use App\Domains\Speech\Contracts\AudioTranscriber;
use App\Domains\Speech\Contracts\TextOptimizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Querschnitts-Sprachfunktionen für JEDES Textfeld (inline, synchron):
 * - transcribe: Audio → Text (Whisper bzw. Fake)
 * - optimize:   Text → sprachlich optimierter Text (LLM bzw. Fake)
 * Bewusst synchron + zustandslos (kein Job/Reverb) — das ist der „neben jedem
 * Textfeld"-Pfad. Die Job-Pipeline (Domains\Speech\Jobs) bleibt für lange
 * Aufnahmen mit Review/Freigabe.
 */
class SpeechController
{
    public function transcribe(Request $request, AudioTranscriber $transcriber): JsonResponse
    {
        $request->validate([
            'audio' => ['required', 'file', 'max:51200'], // bis 50 MB
        ]);

        // WHY: Audio nur flüchtig auf 'local' ablegen, sofort nach ASR löschen (Datensparsamkeit).
        $path = $request->file('audio')->store('speech/tmp', 'local');

        try {
            $text = $transcriber->transcribe(Storage::disk('local')->path($path));
        } finally {
            Storage::disk('local')->delete($path);
        }

        return response()->json(['text' => trim($text)]);
    }

    public function optimize(Request $request, TextOptimizer $optimizer): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:5000'],
            'context' => ['nullable', 'string', 'max:120'],
        ]);

        return response()->json([
            'text' => $optimizer->optimize($data['text'], $data['context'] ?? null),
        ]);
    }
}
