<?php

use App\Models\Asignatura;
use App\Models\Tema;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

/*
|--------------------------------------------------------------------------
| Procesamiento de Audio Feature Tests
|--------------------------------------------------------------------------
*/

// ==================== HELPERS ====================




// ==================== SUBIDA DE AUDIO ====================

test('usuario puede subir un archivo de audio válido', function () {
    // Mock the IA service to avoid real HTTP calls
    Http::fake([
        '*' => Http::response(['estado' => 'ENCOLADO'], 200),
    ]);

    $usuario = Usuario::factory()->create();
    $tema = createTemaPara($usuario);

    $file = UploadedFile::fake()->create('test.mp3', 1000, 'audio/mpeg');

    $response = $this->postJson("/api/temas/{$tema->id_tema}/procesar-audio", [
        'audio' => $file,
        'idioma' => 'auto',
    ], authHeaders($usuario));

    $response->assertStatus(200)
        ->assertJsonStructure(['uuid', 'estado', 'message']);

    $this->assertDatabaseHas('transcripciones', [
        'id_tema' => $tema->id_tema,
        'estado' => 'ENCOLADO',
    ]);
});

test('subir audio requiere archivo', function () {
    $usuario = Usuario::factory()->create();
    $tema = createTemaPara($usuario);

    $response = $this->postJson("/api/temas/{$tema->id_tema}/procesar-audio", [
        'idioma' => 'auto',
    ], authHeaders($usuario));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['audio']);
});

test('subir audio requiere tema existente', function () {
    $usuario = Usuario::factory()->create();

    $file = UploadedFile::fake()->create('test.mp3', 1000, 'audio/mpeg');

    $response = $this->postJson('/api/temas/999/procesar-audio', [
        'audio' => $file,
        'idioma' => 'auto',
    ], authHeaders($usuario));

    $response->assertStatus(404);
});

test('subir audio requiere autenticación', function () {
    $tema = createTemaPara(Usuario::factory()->create());

    $file = UploadedFile::fake()->create('test.mp3', 1000, 'audio/mpeg');

    $response = $this->postJson("/api/temas/{$tema->id_tema}/procesar-audio", [
        'audio' => $file,
        'idioma' => 'auto',
    ]);

    $response->assertStatus(401);
});

test('usuario no puede subir audio a tema de asignatura ajena', function () {
    $usuario1 = Usuario::factory()->create();
    $usuario2 = Usuario::factory()->create();

    $tema = createTemaPara($usuario1);

    $file = UploadedFile::fake()->create('test.mp3', 1000, 'audio/mpeg');

    $response = $this->postJson("/api/temas/{$tema->id_tema}/procesar-audio", [
        'audio' => $file,
        'idioma' => 'auto',
    ], authHeaders($usuario2));

    $response->assertStatus(404);
});

test('subir audio crea registro de transcripción', function () {
    Http::fake([
        '*' => Http::response(['estado' => 'ENCOLADO'], 200),
    ]);

    $usuario = Usuario::factory()->create();
    $tema = createTemaPara($usuario);

    $file = UploadedFile::fake()->create('clase.mp3', 1000, 'audio/mpeg');

    $response = $this->postJson("/api/temas/{$tema->id_tema}/procesar-audio", [
        'audio' => $file,
        'idioma' => 'auto',
    ], authHeaders($usuario));

    $response->assertStatus(200);

    // Verify the transcription record was created
    $this->assertDatabaseHas('transcripciones', [
        'id_tema' => $tema->id_tema,
    ]);

    // The record should have a UUID reference
    $transcripcion = \App\Models\Transcripcion::where('id_tema', $tema->id_tema)->first();
    expect($transcripcion->uuid_referencia)->not()->toBeNull();
});
