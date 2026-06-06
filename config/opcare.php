<?php

return [

    /*
    | Disk für Bewohner-Dokumente/Fotos (spatie media-library). Default 'media' (lokales Dateisystem,
    | privat) — in Prod auf 'minio' (S3-kompatibel, self-hosted) umstellbar, ohne Code-Änderung.
    */
    'media_disk' => env('OPCARE_MEDIA_DISK', 'media'),

    /*
    | Aufbewahrungsfrist medizinischer Dokumente in Jahren (§ 630f Abs. 3 BGB: 10 Jahre; bei
    | absehbaren Haftungsansprüchen faktisch 30, § 199 BGB). Steuert das automatische Löschdatum.
    */
    'media_retention_years' => (int) env('OPCARE_MEDIA_RETENTION_YEARS', 10),

];
