<?php

return [
    'online_wahl_aktiv' => (bool) env('VOTING_ONLINE_WAHL', false),

    // WHY: „gebaut & stillgelegt"-Schalter (docs/INBETRIEBNAHME.md §6). Der Modus GeheimKrypto
    // (blind-signierter Token, Server-/Root-Unverkettbarkeit) ist als Naht vorhanden, aber bis zur
    // Implementierung der Krypto-Härtung gesperrt — niemals als „aktiv" vortäuschen.
    'krypto_unverkettbarkeit_aktiv' => (bool) env('VOTING_KRYPTO_UNVERKETTBARKEIT', false),
];
