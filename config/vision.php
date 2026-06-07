<?php

return [
    'url' => env('VISION_MCP_URL', 'http://localhost:8001'),
    'token' => env('VISION_MCP_TOKEN', ''),
    'fake' => (bool) env('VISION_FAKE', env('SPEECH_FAKE', false)),
];
