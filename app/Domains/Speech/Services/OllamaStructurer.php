<?php

namespace App\Domains\Speech\Services;

use App\Domains\Speech\Contracts\SisStructurer;
use App\Domains\Speech\Data\SisVorschlagData;
use Illuminate\Support\Facades\Http;

class OllamaStructurer implements SisStructurer
{
    public function structure(string $transcript, string $kontext): SisVorschlagData
    {
        $prompt = <<<PROMPT
        Du strukturierst deutsche Pflegedokumentation in SIS-Themenfelder.
        Erlaubte Themenfelder: kognition, mobilitaet, krankheitsbezogen, selbstversorgung, soziale_beziehungen, wohnen.
        Kontext-Hinweis: {$kontext}.
        Gib AUSSCHLIESSLICH JSON zurück im Schema:
        {"felder":[{"themenfeld":"<eines der erlaubten>","freitext":"<text>"}]}
        Transkript: {$transcript}
        PROMPT;

        $response = Http::timeout(config('speech.ollama.timeout'))
            ->post(rtrim(config('speech.ollama.url'), '/').'/api/generate', [
                'model' => config('speech.ollama.model'),
                'prompt' => $prompt,
                'format' => 'json',
                'stream' => false,
            ])
            ->throw();

        $payload = json_decode($response->json('response'), true, 512, JSON_THROW_ON_ERROR);

        // WHY: LLM-Output wird gegen das DTO-Schema validiert (In-Regel auf themenfeld),
        // bevor irgendetwas persistiert wird — nie ungeprüft in Domänen-Tabellen.
        return SisVorschlagData::validateAndCreate($payload);
    }
}
