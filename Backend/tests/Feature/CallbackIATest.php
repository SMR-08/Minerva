<?php

use App\Models\Transcripcion;
use App\Models\Usuario;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

/*
|--------------------------------------------------------------------------
| Callback IA Feature Tests — Cola Unificada
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    Config::set('audio.ia.callback_secret', 'test-secret');
});

function crearTranscripcionConEstado(string $estado = 'PROCESANDO'): Transcripcion
{
    $usuario = Usuario::factory()->create();
    $tema = createTemaPara($usuario);

    return Transcripcion::create([
        'id_tema' => $tema->id_tema,
        'uuid_referencia' => fake()->uuid(),
        'nombre_archivo_original' => 'test.mp3',
        'estado' => $estado,
    ]);
}

// ==================== CALLBACK COMPLETADO ====================

test('callback COMPLETADO actualiza transcripción con resultado', function () {
    Event::fake();
    $transcripcion = crearTranscripcionConEstado('PROCESANDO');

    $response = $this->postJson('/api/ia/callback', [
        'uuid' => $transcripcion->uuid_referencia,
        'estado' => 'COMPLETADO',
        'resultado' => [
            'transcripcion' => [
                ['hablante' => 'Profesor', 'inicio' => 0, 'fin' => 5, 'texto' => 'Hola clase'],
            ],
            'metricas_rendimiento' => [
                'duracion_audio_segundos' => 300,
                'tiempo_procesamiento_total_segundos' => 45,
            ],
        ],
    ], ['X-Callback-Secret' => 'test-secret']);

    $response->assertOk();

    $transcripcion->refresh();
    expect($transcripcion->estado)->toBe('COMPLETADO');
    expect($transcripcion->texto_plano)->toBe('Hola clase');
    expect($transcripcion->duracion_segundos)->toBe(300.0);
});

// ==================== CALLBACK RESUMIENDO ====================

test('callback RESUMIENDO actualiza estado a RESUMIENDO', function () {
    Event::fake();
    $transcripcion = crearTranscripcionConEstado('COMPLETADO');

    $response = $this->postJson('/api/ia/callback', [
        'uuid' => $transcripcion->uuid_referencia,
        'estado' => 'RESUMIENDO',
    ], ['X-Callback-Secret' => 'test-secret']);

    $response->assertOk();

    $transcripcion->refresh();
    expect($transcripcion->estado)->toBe('RESUMIENDO');
    expect($transcripcion->etapa_actual)->toBe('RESUMEN');
});

// ==================== CALLBACK LISTO ====================

test('callback LISTO guarda resumen_ia', function () {
    Event::fake();
    $transcripcion = crearTranscripcionConEstado('RESUMIENDO');

    $resumen = "## Resumen\n\n### Tema principal\nDerivadas parciales.";

    $response = $this->postJson('/api/ia/callback', [
        'uuid' => $transcripcion->uuid_referencia,
        'estado' => 'LISTO',
        'resumen' => $resumen,
    ], ['X-Callback-Secret' => 'test-secret']);

    $response->assertOk();

    $transcripcion->refresh();
    expect($transcripcion->estado)->toBe('LISTO');
    expect($transcripcion->resumen_ia)->toBe($resumen);
    expect($transcripcion->etapa_actual)->toBeNull();
});

// ==================== CALLBACK FALLIDO ====================

test('callback FALLIDO guarda error', function () {
    Event::fake();
    $transcripcion = crearTranscripcionConEstado('PROCESANDO');

    $response = $this->postJson('/api/ia/callback', [
        'uuid' => $transcripcion->uuid_referencia,
        'estado' => 'FALLIDO',
        'error' => 'GPU out of memory',
    ], ['X-Callback-Secret' => 'test-secret']);

    $response->assertOk();

    $transcripcion->refresh();
    expect($transcripcion->estado)->toBe('FALLIDO');
    expect($transcripcion->error_mensaje)->toBe('GPU out of memory');
});

// ==================== CALLBACK PROCESANDO ====================

test('callback PROCESANDO actualiza progreso y etapa', function () {
    Event::fake();
    $transcripcion = crearTranscripcionConEstado('ENCOLADO');

    $response = $this->postJson('/api/ia/callback', [
        'uuid' => $transcripcion->uuid_referencia,
        'estado' => 'PROCESANDO',
        'resultado' => [
            'progreso' => 50,
            'etapa' => 'DIARIZACION',
        ],
    ], ['X-Callback-Secret' => 'test-secret']);

    $response->assertOk();

    $transcripcion->refresh();
    expect($transcripcion->estado)->toBe('PROCESANDO');
});

// ==================== SEGURIDAD ====================

test('callback sin secret retorna 401', function () {
    $transcripcion = crearTranscripcionConEstado();

    $response = $this->postJson('/api/ia/callback', [
        'uuid' => $transcripcion->uuid_referencia,
        'estado' => 'COMPLETADO',
        'resultado' => ['transcripcion' => [], 'metricas_rendimiento' => []],
    ], ['X-Callback-Secret' => 'wrong-secret']);

    $response->assertStatus(401);
});

test('callback con secret incorrecto retorna 401', function () {
    $transcripcion = crearTranscripcionConEstado();

    $response = $this->postJson('/api/ia/callback', [
        'uuid' => $transcripcion->uuid_referencia,
        'estado' => 'COMPLETADO',
        'resultado' => ['transcripcion' => [], 'metricas_rendimiento' => []],
    ]);

    $response->assertStatus(401);
});

// ==================== ENDPOINT DESCARGA ====================

test('endpoint descarga sin auth retorna 401', function () {
    $response = $this->getJson('/api/internal/audio-download/test-uuid');
    $response->assertStatus(401);
});

test('endpoint descarga con auth retorna 404 si no hay archivo', function () {
    Config::set('audio.ia.callback_secret', 'test-secret');

    $response = $this->get('/api/internal/audio-download/nonexistent-uuid', [
        'Authorization' => 'Bearer test-secret',
    ]);

    $response->assertStatus(404);
});
