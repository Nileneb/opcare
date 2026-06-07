<?php

namespace App\Http\Controllers;

use App\Domains\Accounting\Models\Gefahrstoff;
use App\Domains\Capture\Models\LieferscheinAnalyse;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Import\Models\ImportBatch;
use App\Domains\Masterdata\Models\MediaShare;
use App\Domains\Masterdata\Models\Resident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Liefert ein Bewohner-Dokument/Foto sowie Gefahrstoff-SDB über eine signierte, ablaufende Route
 * aus (disk-agnostisch: lokal oder MinIO). Tenant-Scope wird über das Trägermodell erzwungen
 * (IDOR-Schutz); bei Freigabe-Links wird der Zugriff protokolliert.
 */
class MediaDownloadController extends Controller
{
    public function __invoke(Request $request, Media $media): StreamedResponse
    {
        $owner = $media->model;
        // WHY(§ 6 Abs. 12 Nr. 5 GefStoffV): SDB gehört einem Gefahrstoff, nicht einem Resident —
        // beide Owner-Typen tenant-scoped erlauben, sonst 403 bei SDB-Download.
        $ownerTenantId = match (true) {
            $owner instanceof Resident => $owner->tenant_id,
            $owner instanceof Gefahrstoff => $owner->tenant_id,
            $owner instanceof LieferscheinAnalyse => $owner->tenant_id,
            $owner instanceof ImportBatch => $owner->tenant_id,
            default => null,
        };
        abort_unless($ownerTenantId !== null && (int) $ownerTenantId === (int) app(CurrentTenant::class)->id(), 403);

        if ($request->filled('share')) {
            $share = MediaShare::where('media_id', $media->id)->findOrFail($request->integer('share'));
            abort_unless($share->aktiv(), 403);
            $share->update(['accessed_at' => now()]);
        }

        return Storage::disk($media->disk)->download($media->getPathRelativeToRoot(), $media->name);
    }
}
