<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Data\ShiftAssignmentData;
use App\Domains\Scheduling\Models\ShiftAssignment;

class AssignShift
{
    // WHY: Dienstplan-Zuweisung ist idempotent — derselbe (user, shift, tag) darf nur einmal existieren.
    // whereDate() statt direkte Gleichheit, weil SQLite dates als Text speichert und der date-Cast
    // '2026-06-15' zu '2026-06-15 00:00:00' erweitert — SELECT würde sonst keinen Treffer liefern.
    public function handle(ShiftAssignmentData $data): ShiftAssignment
    {
        $existing = ShiftAssignment::where('user_id', $data->user_id)
            ->where('shift_id', $data->shift_id)
            ->whereDate('dienst_am', $data->dienst_am)
            ->first();

        if ($existing) {
            return $existing;
        }

        return ShiftAssignment::create([
            'user_id' => $data->user_id,
            'shift_id' => $data->shift_id,
            'dienst_am' => $data->dienst_am,
            'notiz' => $data->notiz,
        ]);
    }
}
