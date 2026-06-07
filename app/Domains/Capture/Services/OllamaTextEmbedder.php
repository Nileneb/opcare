<?php

namespace App\Domains\Capture\Services;

use App\Domains\Capture\Contracts\TextEmbedder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Berechnet Text-Embeddings lokal via Ollama (/api/embeddings) — kein Cloud-Zugriff (DSGVO-konform).
 * Null = Modell nicht verfügbar; der Fehler wird geloggt, nicht verschluckt.
 */
class OllamaTextEmbedder implements TextEmbedder
{
    public function embed(string $text): ?array
    {
        try {
            $response = Http::timeout((int) config('speech.ollama.timeout'))
                ->post(rtrim((string) config('speech.ollama.url'), '/').'/api/embeddings', [
                    'model' => $this->model(),
                    'prompt' => $text,
                ])
                ->throw();

            /** @var mixed $embedding */
            $embedding = $response->json('embedding');

            if (! is_array($embedding)) {
                Log::warning('OllamaTextEmbedder: Antwort enthält kein embedding-Feld', [
                    'model' => $this->model(),
                    'response' => $response->body(),
                ]);

                return null;
            }

            return $embedding;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    public function model(): string
    {
        return (string) config('speech.capture.embedding_model');
    }
}
