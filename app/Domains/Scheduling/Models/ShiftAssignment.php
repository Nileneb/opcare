<?php

namespace App\Domains\Scheduling\Models;

use App\Domains\Identity\Models\User;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftAssignment extends BaseModel
{
    protected $fillable = ['tenant_id', 'user_id', 'shift_id', 'dienst_am', 'notiz'];

    protected $casts = ['dienst_am' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
