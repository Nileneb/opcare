<?php

namespace App\Domains\Capture\Services;

use App\Domains\Capture\Contracts\LieferscheinVlmAnalyzer;
use App\Domains\Capture\Data\LieferscheinExtraktion;
use Illuminate\Support\Facades\Http;

/**
 * VLM-gestützte Lieferschein-Extraktion über ein vision-fähiges Ollama-Modell (`/api/generate` mit `images`).
 * Strenger Extraktions-Prompt: nur strukturiertes JSON, fehlende Angaben bleiben null, KEINE erfundenen Werte.
 * Das Ergebnis ist ein Vorschlag und wird gegen das DTO normalisiert — nie ungeprüft persistiert.
 */
class OllamaLieferscheinAnalyzer implements LieferscheinVlmAnalyzer
{
    public function analysiere(string $imageBase64, string $mimeType): LieferscheinExtraktion
    {
        $prompt = <<<'PROMPT'
        Du extrahierst Daten aus einem fotografierten Lieferschein oder einer Rechnung einer Pflegeeinrichtung.
        Gib AUSSCHLIESSLICH JSON in genau diesem Schema zurück:
        {"lieferant":"<text|null>","datum":"<YYYY-MM-DD|null>","lieferschein_nr":"<text|null>",
         "konfidenz":<0..1>,
         "positionen":[{"text":"<Artikelbezeichnung>","menge":<zahl|null>,"einheit":"<z.B. Stück,Karton,kg|null>",
                         "einzelpreis":<zahl|null>,"charge_nr":"<text|null>","mhd":"<YYYY-MM-DD|null>"}]}
        Regeln: Zahlen als Dezimalzahl mit Punkt (keine Einheiten/Währungszeichen im Zahlfeld).
        Fehlt eine Angabe, setze null. ERFINDE KEINE Fakten — gib nur zurück, was im Bild eindeutig lesbar ist.
        Keine Erklärtexte, nur das JSON.
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

        return LieferscheinExtraktion::vonRoh($this->normalisiere($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalisiere(array $payload): array
    {
        foreach ($payload['positionen'] ?? [] as $i => $pos) {
            if (isset($pos['menge'])) {
                $payload['positionen'][$i]['menge'] = $this->zahl($pos['menge']);
            }
            if (isset($pos['einzelpreis'])) {
                $payload['positionen'][$i]['einzelpreis'] = $this->zahl($pos['einzelpreis']);
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
            $clean = str_replace([' ', '€', 'EUR', 'kg', 'g', 'l', 'ml'], '', $wert);
            $clean = str_replace(',', '.', $clean);

            return is_numeric($clean) ? (float) $clean : null;
        }

        return null;
    }
}
