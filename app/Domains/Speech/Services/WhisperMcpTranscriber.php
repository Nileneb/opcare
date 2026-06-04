<?php

namespace App\Domains\Speech\Services;

use App\Domains\Speech\Contracts\AudioTranscriber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Lokaler WhisperX-Dienst (whisperx-mcp) — angesprochen über das stateless
 * MCP-Streamable-HTTP-Tool `transcribe-audio` (POST {url}/mcp/, Bearer-Token).
 * Liefert reinen Text; Diarization-Labels (SPEAKER_xx:) werden fürs Diktat entfernt.
 */
class WhisperMcpTranscriber implements AudioTranscriber
{
    public function transcribe(string $absolutePath): string
    {
        $payload = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'transcribe-audio',
                'arguments' => [
                    'file' => [
                        'fileName' => basename($absolutePath),
                        'mimeType' => $this->mimeType($absolutePath),
                        'base64' => base64_encode(file_get_contents($absolutePath)),
                    ],
                    'output_format' => 'txt',
                ],
            ],
        ];

        $response = Http::timeout(config('speech.whisper.timeout'))
            ->withToken((string) config('speech.whisper.token'))
            ->withHeaders(['Accept' => 'application/json, text/event-stream'])
            ->post(rtrim((string) config('speech.whisper.url'), '/').'/mcp/', $payload)
            ->throw();

        // MCP-Antwort: result.content[0].text  (bei isError → Fehlertext)
        $text = (string) ($response->json('result.content.0.text') ?? '');

        return $this->stripSpeakerLabels($text);
    }

    /** Entfernt führende „SPEAKER_00: "-Labels (Diarization) und verdichtet zu Fließtext. */
    public function stripSpeakerLabels(string $text): string
    {
        $lines = preg_split('/\r?\n/', trim($text)) ?: [];

        $clean = array_map(
            fn (string $line) => trim(preg_replace('/^\s*SPEAKER_\d+:\s*/u', '', $line)),
            $lines,
        );

        return Str::of(implode(' ', array_filter($clean)))->squish()->value();
    }

    private function mimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'webm' => 'audio/webm',
            'ogg', 'oga' => 'audio/ogg',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'm4a' => 'audio/mp4',
            'flac' => 'audio/flac',
            'mp4' => 'video/mp4',
            default => 'application/octet-stream',
        };
    }
}
