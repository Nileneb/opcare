<?php

namespace App\Support\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait Versionable
{
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('superseded_by');
    }

    public function isSuperseded(): bool
    {
        return $this->superseded_by !== null;
    }

    /**
     * Erzeugt eine neue Version mit den geänderten Attributen, markiert die
     * aktuelle als abgelöst und verkettet sie via superseded_by.
     */
    public function reviseWith(array $attributes): static
    {
        $new = $this->replicate(['superseded_by', 'created_at', 'updated_at']);
        $new->fill($attributes);
        $new->version = $this->version + 1;
        if (in_array('status', $this->getFillable(), true)) {
            $new->status = 'aktiv';
        }
        $new->save();

        $update = ['superseded_by' => $new->id];
        if (in_array('status', $this->getFillable(), true)) {
            $update['status'] = 'abgelöst';
        }
        $this->forceFill($update)->save();

        return $new;
    }
}
