<?php

namespace App\Http\Controllers;

use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\MediaShare;
use App\Domains\Masterdata\Models\Resident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Liefert ein Bewohner-Dokument/Foto über eine signierte, ablaufende Route aus (disk-agnostisch: lokal
 * oder MinIO). Tenant-Scope wird über das verknüpfte Resident-Modell erzwungen (IDOR-Schutz); bei
 * Freigabe-Links wird der Zugriff protokolliert.
 */
class MediaDownloadController extends Controller
{
    public function __invoke(Request $request, Media $media): StreamedResponse
    {
        // Tenant-Scope: das Trägermodell (Resident) unterliegt dem globalen TenantScope.
        $owner = $media->model;
        abort_unless($owner instanceof Resident && (int) $owner->tenant_id === (int) app(CurrentTenant::class)->id(), 403);

        if ($request->filled('share')) {
            $share = MediaShare::where('media_id', $media->id)->findOrFail($request->integer('share'));
            abort_unless($share->aktiv(), 403);
            $share->update(['accessed_at' => now()]);
        }

        return Storage::disk($media->disk)->download($media->getPathRelativeToRoot(), $media->name);
    }
}
