<?php

return [
    // WHY: im Dev/Test ohne Whisper/Ollama lauffähig — bindet Fake-Adapter (Demo-Pipeline).
    'fake' => (bool) env('SPEECH_FAKE', false),

    'whisper' => [
        'url' => env('WHISPER_URL', 'http://127.0.0.1:9000'),
        'model' => env('WHISPER_MODEL', 'large-v3'),
        'timeout' => (int) env('WHISPER_TIMEOUT', 120),
    ],
    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://192.168.178.11:11434'),
        'model' => env('OLLAMA_MODEL', 'llama3.1:8b'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 120),
    ],
];
