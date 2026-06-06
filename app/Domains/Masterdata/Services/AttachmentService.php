<?php

namespace App\Domains\Masterdata\Services;

use App\Domains\Masterdata\Enums\DokumentKategorie;
use App\Domains\Masterdata\Models\MediaShare;
use App\Domains\Masterdata\Models\Resident;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Bewohner-Dokumente/Fotos: Upload in die spatie-„documents"-Collection (Disk konfigurierbar → MinIO),
 * mit Kategorie, Aufbewahrungsfrist (§ 630f BGB) und optionaler Foto-Einwilligung in den custom_properties.
 * Freigabe per signierter, ablaufender Route (disk-agnostisch, protokolliert) statt öffentlicher URL.
 */
class AttachmentService
{
    public function upload(Resident $resident, UploadedFile $file, DokumentKategorie $kategorie, ?string $einwilligungVon = null): Media
    {
        $retentionUntil = $kategorie->istMedizinisch()
            ? today()->addYears((int) config('opcare.media_retention_years', 10))->toDateString()
            : null;

        return $resident->addMedia($file->getRealPath())
            ->usingFileName($file->hashName())
            ->usingName($file->getClientOriginalName())
            ->withCustomProperties([
                'kategorie' => $kategorie->value,
                'medizinisch' => $kategorie->istMedizinisch(),
                'retention_until' => $retentionUntil,
                'einwilligung_von' => $kategorie->brauchtEinwilligung() ? $einwilligungVon : null,
            ])
            ->toMediaCollection('documents');
    }

    /** Signierte, ablaufende Download-URL + Freigabe-Protokoll (DSGVO-Auditpflicht). */
    public function shareLink(Media $media, int $minutes, string $shareType, string $recipient): string
    {
        $share = MediaShare::create([
            'media_id' => $media->id,
            'shared_by' => auth()->id(),
            'share_type' => $shareType,
            'recipient_name' => $recipient,
            'expires_at' => now()->addMinutes($minutes),
        ]);

        return URL::temporarySignedRoute('media.download', now()->addMinutes($minutes), [
            'media' => $media->id,
            'share' => $share->id,
        ]);
    }

    public function delete(Media $media): void
    {
        $media->delete();
    }
}
