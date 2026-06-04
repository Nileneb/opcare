<?php

namespace App\Domains\Speech\Services;

use App\Domains\Speech\Contracts\TextOptimizer;
use Illuminate\Support\Facades\Http;

class OllamaTextOptimizer implements TextOptimizer
{
    public function optimize(string $text, ?string $context = null): string
    {
        $hinweis = $context ? "Kontext (Lebensbereich): {$context}.\n" : '';

        $prompt = <<<PROMPT
        Du bist Assistenz für deutsche Pflegedokumentation. Formuliere den folgenden Text
        sprachlich klar, sachlich und fachgerecht um. ERFINDE KEINE Fakten, ergänze nichts
        inhaltlich Neues, lass nichts weg. Gib AUSSCHLIESSLICH den überarbeiteten Text zurück.
        {$hinweis}
        Text: {$text}
        PROMPT;

        $response = Http::timeout(config('speech.ollama.timeout'))
            ->post(rtrim(config('speech.ollama.url'), '/').'/api/generate', [
                'model' => config('speech.ollama.model'),
                'prompt' => $prompt,
                'stream' => false,
            ])
            ->throw();

        return trim((string) $response->json('response')) ?: $text;
    }
}
