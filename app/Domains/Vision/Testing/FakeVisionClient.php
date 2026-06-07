<?php

namespace App\Domains\Vision\Testing;

use App\Domains\Vision\Contracts\VisionClient;

class FakeVisionClient implements VisionClient
{
    public function detect(string $imageB64, string $modelPath, float $conf = 0.25): array
    {
        return [
            'detections' => [
                ['label' => 'box', 'confidence' => 0.9, 'bbox' => [0.5, 0.5, 0.2, 0.2]],
            ],
            'counts' => ['box' => 3],
            'model_used' => 'fake',
        ];
    }

    public function autoAnnotate(string $imageB64, bool $useSam = true): array
    {
        return [
            'suggestions' => [
                ['label' => 'box', 'bbox' => [0.1, 0.1, 0.3, 0.3], 'confidence' => 0.85],
            ],
        ];
    }

    public function train(string $zipB64, string $tenantRef, array $opts = []): string
    {
        return 'job-fake-1';
    }

    public function trainStatus(string $jobId): array
    {
        return [
            'status' => 'completed',
            'model_path' => '/models/fake/v1.pt',
            'class_names' => ['box'],
            'metrics' => [],
        ];
    }
}
