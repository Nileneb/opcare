<?php

namespace App\Domains\Vision\Services;

use App\Domains\Vision\Contracts\VisionClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class HttpVisionClient implements VisionClient
{
    public function detect(string $imageB64, string $modelPath, float $conf = 0.25): array
    {
        return $this->call('detect', [
            'image_base64' => $imageB64,
            'model_path' => $modelPath,
            'confidence' => $conf,
        ]);
    }

    public function autoAnnotate(string $imageB64, bool $useSam = true): array
    {
        return $this->call('auto_annotate', [
            'image_base64' => $imageB64,
            'use_sam' => $useSam,
        ]);
    }

    public function train(string $zipB64, string $tenantId, array $opts = []): string
    {
        $payload = ['dataset_zip_base64' => $zipB64, 'tenant_id' => $tenantId];

        foreach (['base_model', 'epochs', 'batch_size', 'image_size'] as $key) {
            if (isset($opts[$key])) {
                $payload[$key] = $opts[$key];
            }
        }

        $result = $this->call('train', $payload);

        return (string) ($result['job_id'] ?? throw new RuntimeException('vision-mcp: train lieferte keine job_id'));
    }

    public function trainStatus(string $jobId): array
    {
        return $this->call('train_status', ['job_id' => $jobId]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function call(string $tool, array $arguments): array
    {
        $url = rtrim((string) config('vision.url'), '/').'/mcp/';

        $response = Http::withToken((string) config('vision.token'))
            ->timeout(120)
            ->post($url, [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/call',
                'params' => [
                    'name' => $tool,
                    'arguments' => $arguments,
                ],
            ]);

        if ($response->failed()) {
            $exception = new RuntimeException(
                "vision-mcp HTTP-Fehler {$response->status()} beim Tool '{$tool}': {$response->body()}"
            );
            report($exception);
            Log::error('vision-mcp request failed', ['tool' => $tool, 'status' => $response->status()]);
            throw $exception;
        }

        // StreamableHTTP: Ergebnis als JSON-Text in result.content[0].text
        $text = $response->json('result.content.0.text');

        if (! is_string($text)) {
            $exception = new RuntimeException(
                "vision-mcp: unerwartetes Antwortformat für Tool '{$tool}' — kein result.content[0].text"
            );
            report($exception);
            Log::error('vision-mcp unexpected response', ['tool' => $tool, 'body' => $response->body()]);
            throw $exception;
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            $exception = new RuntimeException(
                "vision-mcp: result.content[0].text ist kein valides JSON für Tool '{$tool}'"
            );
            report($exception);
            Log::error('vision-mcp json parse failed', ['tool' => $tool, 'text' => $text]);
            throw $exception;
        }

        return $decoded;
    }
}
