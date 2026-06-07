<?php

return [
    'url' => env('VISION_MCP_URL', 'http://localhost:8001'),
    'token' => env('VISION_MCP_TOKEN', ''),
    'fake' => (bool) env('VISION_FAKE', env('SPEECH_FAKE', false)),
    'default_model' => env('VISION_DEFAULT_MODEL', '/models/base/yolo11n.pt'),
    // WHY(Inbetriebnahme-Schalter): Training stillgelegt bis Zulassung — siehe docs/INBETRIEBNAHME.md §5.
    'training_aktiv' => (bool) env('VISION_TRAINING_AKTIV', false),
];
