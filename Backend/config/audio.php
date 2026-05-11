<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuración de Procesamiento de Audio
    |--------------------------------------------------------------------------
    |
    | Configuración para la arquitectura "Patata Caliente" con streaming
    | y actualizaciones SSE en tiempo real.
    |
    */

    // Límites de upload
    'max_size_mb' => env('AUDIO_MAX_SIZE_MB', 2048), // 2GB por defecto
    'chunk_size_mb' => env('AUDIO_CHUNK_SIZE_MB', 10),

    // Formatos permitidos
    'allowed_extensions' => ['wav', 'mp3', 'm4a', 'flac', 'ogg'],

    // Rutas (legacy, para compatibilidad)
    'rutas' => [
        'entrada' => env('RUTA_ENTRADA', '/app/compartido/entrada'),
        'salida' => env('RUTA_SALIDA', '/app/compartido/salida'),
    ],

    // Configuración de IA — fuente de verdad UNIFICADA
    // Todas las referencias a servicios de IA deben usar config('audio.ia.*')
    // Variables de entorno: IA_UPLOAD_URL, LARAVEL_URL, IA_CALLBACK_SECRET, AI_TIMEOUT, AI_INPUT_PATH
    'ia' => [
        // URL para upload streaming (ASR)
        'upload_url' => env('IA_UPLOAD_URL', 'http://minerva-asr:8000'),

        // URL de Laravel vista desde IA (para callbacks)
        'laravel_url' => env('LARAVEL_URL', 'http://laravel-app:80'),

        // Secret para autenticar callbacks de IA
        'callback_secret' => env('IA_CALLBACK_SECRET', 'cambia_esto_en_produccion'),

        // Timeout para procesamiento (segundos)
        'timeout' => env('AI_TIMEOUT', 7200), // 2 horas

        // Ruta de entrada para archivos de audio (modo legacy/admin)
        'input_path' => env('AI_INPUT_PATH', '/app/compartido/entrada'),
    ],

    // SSE (Server-Sent Events)
    'sse' => [
        // Intervalo entre heartbeats (segundos)
        'heartbeat_seconds' => env('SSE_HEARTBEAT_SECONDS', 30),

        // Intervalo de sondeo entre actualizaciones (microsegundos)
        'poll_interval_microseconds' => env('SSE_POLL_INTERVAL_MICROSECONDS', 2000000),  // 2 segundos

        // Timeout máximo de conexión (segundos)
        'timeout_seconds' => 7200, // 2 horas
    ],

    // Cola de procesamiento
    'queue' => [
        // Conexión de cola
        'connection' => env('QUEUE_CONNECTION', 'redis'),

        // Nombre de la cola
        'queue' => 'process_audio',

        // Máximo de intentos
        'max_attempts' => 3,
    ],
];
