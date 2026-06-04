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
];
