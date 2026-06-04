<?php

use App\Domains\Qdvs\Specs\CsvQdvsSpec;

return [
    'default_spec' => 'csv-v1',
    'specs' => [
        CsvQdvsSpec::class,
        // spätere Specs (XML/DAS-konform) hier registrieren
    ],
    'disk' => 'local',
    'path' => 'qdvs',

    // DAS-Pflege V03.0 Plausibilitätsregeln (statische Referenzdaten, global, kein tenant_id)
    'rules_csv' => database_path('data/qdvs/das_plausibilitaetsregeln_v03.csv'),
];
