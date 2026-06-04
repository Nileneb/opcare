<?php

namespace App\Domains\Quality\Support;

use App\Domains\Masterdata\Models\Resident;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Cohort
{
    public function __construct(public string $stichtag, public Collection $residents) {}

    public static function atStichtag(string $stichtag): self
    {
        $datum = Carbon::parse($stichtag)->toDateString();

        $residents = Resident::query()
            ->whereDate('aufnahme_am', '<=', $datum)
            ->where(fn ($q) => $q->whereNull('entlassung_am')->orWhereDate('entlassung_am', '>=', $datum))
            ->get();

        return new self($datum, $residents);
    }

    public function count(): int
    {
        return $this->residents->count();
    }

    public function ids(): array
    {
        return $this->residents->pluck('id')->all();
    }
}
