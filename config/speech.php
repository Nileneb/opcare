<?php

return [
    // WHY: im Dev/Test ohne Whisper/Ollama lauffähig — bindet Fake-Adapter (Demo-Pipeline).
    'fake' => (bool) env('SPEECH_FAKE', false),

    'whisper' => [
        // 'mcp' = lokaler whisperx-mcp (Streamable-HTTP /mcp/, Bearer-Token);
        // 'asr' = generischer whisper-asr-webservice (/asr Multipart).
        'driver' => env('WHISPER_DRIVER', 'mcp'),
        'url' => env('WHISPER_URL', 'http://localhost:8000'),
        'token' => env('WHISPER_TOKEN'),
        'model' => env('WHISPER_MODEL', 'large-v3-turbo'),
        'timeout' => (int) env('WHISPER_TIMEOUT', 180),
    ],
    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        'model' => env('OLLAMA_MODEL', 'qwen3.5:latest'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 120),
    ],
    // VLM-Beleg-Capture: vision-fähiges Ollama-Modell (nutzt dieselbe OLLAMA_URL/-timeout).
    'capture' => [
        'model' => env('CAPTURE_VLM_MODEL', 'qwen2.5vl:latest'),
    ],
];
