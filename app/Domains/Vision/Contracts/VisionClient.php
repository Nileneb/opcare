<?php

namespace App\Domains\Vision\Contracts;

interface VisionClient
{
    /**
     * Führt YOLO-Objekterkennung auf einem Base64-kodierten Bild aus.
     *
     * @return array{detections: array<int, array{label: string, confidence: float, bbox: array<int, float>}>, counts: array<string, int>, model_used: string}
     */
    public function detect(string $imageB64, string $modelPath, float $conf = 0.25): array;

    /**
     * Automatische Annotation (mit optionalem SAM-Segment-Anything).
     *
     * @return array{suggestions: array<int, mixed>}
     */
    public function autoAnnotate(string $imageB64, bool $useSam = true): array;

    /**
     * Startet einen Trainings-Job und gibt die Job-ID zurück.
     *
     * @param  array<string, mixed>  $opts
     */
    public function train(string $zipB64, string $tenantRef, array $opts = []): string;

    /**
     * @return array{status: string, model_path?: string, class_names?: array<int, string>, metrics?: array<string, mixed>}
     */
    public function trainStatus(string $jobId): array;
}
