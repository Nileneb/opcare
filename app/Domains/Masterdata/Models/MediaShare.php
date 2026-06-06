<?php

namespace App\Domains\Masterdata\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Protokoll einer Datei-Freigabe (DSGVO-Auditpflicht bei Weitergabe von Gesundheitsdaten).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $media_id
 * @property int $shared_by
 * @property string $share_type
 * @property string $recipient_name
 * @property Carbon $expires_at
 * @property Carbon|null $accessed_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Media $media
 * @property-read User $sharer
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaShare newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaShare newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaShare query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaShare whereAccessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaShare whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaShare whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaShare whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaShare whereMediaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaShare whereRecipientName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaShare whereRevokedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaShare whereShareType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaShare whereSharedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaShare whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaShare whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class MediaShare extends BaseModel
{
    protected $fillable = ['tenant_id', 'media_id', 'shared_by', 'share_type', 'recipient_name', 'expires_at', 'accessed_at', 'revoked_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'accessed_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function sharer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by');
    }

    public function aktiv(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
