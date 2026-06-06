<?php

namespace App\Domains\Capture\Services;

use App\Domains\Capture\Contracts\BelegVlmAnalyzer;
use App\Domains\Capture\Data\BelegExtraktion;
use Illuminate\Support\Facades\Http;

/**
 * VLM-gestützte Beleg-Extraktion über ein vision-fähiges Ollama-Modell (`/api/generate` mit `images`).
 * Strenger Extraktions-Prompt (gleiche Härte wie OllamaTextOptimizer): nur strukturiertes JSON, fehlende
 * Angaben bleiben null, KEINE erfundenen Werte. Das Ergebnis ist ein Vorschlag und wird vor jeder Nutzung
 * gegen das DTO normalisiert — nie ungeprüft persistiert.
 */
class OllamaBelegAnalyzer implements BelegVlmAnalyzer
{
    public function analysiere(string $imageBase64, string $mimeType): BelegExtraktion
    {
        $prompt = <<<'PROMPT'
        Du extrahierst Daten aus einem fotografierten Beleg (Rechnung/Quittung/Kassenbon) einer Pflegeeinrichtung.
        Gib AUSSCHLIESSLICH JSON in genau diesem Schema zurück:
        {"belegtyp":"<rechnung|quittung|kassenbon|sonstiges|null>","datum":"<YYYY-MM-DD|null>","betrag":<zahl|null>,
         "waehrung":"<z.B. EUR>","lieferant":"<text|null>","positionen":[{"text":"<text>","betrag":<zahl|null>}],
         "konfidenz":<0..1>}
        Regeln: Betrag als Dezimalzahl mit Punkt (keine Währungszeichen). Fehlt eine Angabe, setze null.
        ERFINDE KEINE Fakten — gib nur zurück, was im Bild eindeutig lesbar ist. Keine Erklärtexte, nur das JSON.
        PROMPT;

        $response = Http::timeout((int) config('speech.ollama.timeout'))
            ->post(rtrim((string) config('speech.ollama.url'), '/').'/api/generate', [
                'model' => config('speech.capture.model'),
                'prompt' => $prompt,
                'images' => [$imageBase64],
                'format' => 'json',
                'stream' => false,
                'think' => false,
            ])
            ->throw();

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $response->json('response'), true, 512, JSON_THROW_ON_ERROR);

        return BelegExtraktion::from($this->normalisiere($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalisiere(array $payload): array
    {
        if (isset($payload['betrag'])) {
            $payload['betrag'] = $this->zahl($payload['betrag']);
        }
        foreach ($payload['positionen'] ?? [] as $i => $pos) {
            if (isset($pos['betrag'])) {
                $payload['positionen'][$i]['betrag'] = $this->zahl($pos['betrag']);
            }
        }

        return $payload;
    }

    private function zahl(mixed $wert): ?float
    {
        if (is_numeric($wert)) {
            return (float) $wert;
        }
        if (is_string($wert)) {
            // "12,50 €" / "12.50" → 12.50; nicht parsebar → null (kein geratener Wert)
            $clean = str_replace([' ', '€', 'EUR'], '', $wert);
            $clean = str_replace(',', '.', $clean);

            return is_numeric($clean) ? (float) $clean : null;
        }

        return null;
    }
}
