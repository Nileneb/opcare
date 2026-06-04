<?php

namespace App\Domains\Qdvs\Actions;

use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Qdvs\Models\QdvsExport;
use App\Domains\Qdvs\Services\AssemblePackages;
use App\Domains\Qdvs\Services\QdvsValidator;
use App\Domains\Qdvs\Support\SpecRegistry;
use App\Domains\Quality\Support\Cohort;
use Illuminate\Support\Facades\Storage;

class BuildQdvsExport
{
    public function __construct(
        private AssemblePackages $assemble,
        private QdvsValidator $validator,
        private SpecRegistry $registry,
    ) {}

    public function handle(string $stichtag, ?string $specKey = null): QdvsExport
    {
        $spec = $this->registry->get($specKey ?? config('qdvs.default_spec'));
        $tenant = app(CurrentTenant::class)->get();
        $cohort = Cohort::atStichtag($stichtag);
        $packages = $this->assemble->handle($cohort);
        $issues = $this->validator->validate($packages);

        $export = QdvsExport::create([
            'stichtag' => $stichtag,
            'spec' => $spec->key(),
            'bewohner_count' => count($packages),
            'fehler' => collect($issues)->map->toArray()->all(),
            'regel_coverage' => $this->validator->report()?->toSummary(),
            'erstellt_von' => auth()->id(),
            'status' => 'validiert',
        ]);

        if ($this->validator->hatBlockierendeFehler($issues)) {
            $export->update(['status' => 'fehler']);

            return $export;
        }

        $inhalt = $spec->render($packages, $tenant, $stichtag);
        $pfad = trim(config('qdvs.path'), '/').'/'.$tenant->id.'-'.$spec->filename($stichtag);
        Storage::disk(config('qdvs.disk'))->put($pfad, $inhalt);

        $export->update(['status' => 'exportiert', 'pfad' => $pfad]);

        return $export;
    }
}
