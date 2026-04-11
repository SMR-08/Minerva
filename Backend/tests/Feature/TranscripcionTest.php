<?php

use App\Models\Asignatura;
use App\Models\Tema;
use App\Models\Transcripcion;
use App\Models\Usuario;

/*
|--------------------------------------------------------------------------
| Transcripcion Feature Tests
|--------------------------------------------------------------------------
*/

// ==================== HELPERS ====================



// ==================== LISTAR ====================

test('usuario puede listar sus transcripciones', function () {
    $usuario = Usuario::factory()->create();
    $chain = createFullChain($usuario);

    Transcripcion::create([
        'id_tema' => $chain['tema']->id_tema,
        'uuid_referencia' => 'test-uuid-1',
        'estado' => 'COMPLETADO',
        'titulo' => 'Transcripción 1',
        'texto_plano' => 'Hola mundo',
    ]);

    Transcripcion::create([
        'id_tema' => $chain['tema']->id_tema,
        'uuid_referencia' => 'test-uuid-2',
        'estado' => 'COMPLETADO',
        'titulo' => 'Transcripción 2',
        'texto_plano' => 'Segunda transcripción',
    ]);

    $response = $this->getJson('/api/transcripciones', authHeaders($usuario));

    $response->assertStatus(200)
        ->assertJsonCount(2);
});

test('usuario solo ve transcripciones de sus asignaturas', function () {
    $usuario1 = Usuario::factory()->create();
    $usuario2 = Usuario::factory()->create();

    $chain1 = createFullChain($usuario1);
    $chain2 = createFullChain($usuario2);

    Transcripcion::create([
        'id_tema' => $chain1['tema']->id_tema,
        'uuid_referencia' => 'test-uuid-1',
        'estado' => 'COMPLETADO',
        'titulo' => 'Transcripción Usuario 1',
        'texto_plano' => 'Texto 1',
    ]);

    Transcripcion::create([
        'id_tema' => $chain2['tema']->id_tema,
        'uuid_referencia' => 'test-uuid-2',
        'estado' => 'COMPLETADO',
        'titulo' => 'Transcripción Usuario 2',
        'texto_plano' => 'Texto 2',
    ]);

    $response = $this->getJson('/api/transcripciones', authHeaders($usuario1));

    $response->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonFragment(['titulo' => 'Transcripción Usuario 1']);

    $response->assertJsonMissing(['titulo' => 'Transcripción Usuario 2']);
});

test('listar transcripciones requiere autenticación', function () {
    $response = $this->getJson('/api/transcripciones');

    $response->assertStatus(401);
});

// ==================== VER ====================

test('usuario puede ver una transcripción', function () {
    $usuario = Usuario::factory()->create();
    $chain = createFullChain($usuario);

    $transcripcion = Transcripcion::create([
        'id_tema' => $chain['tema']->id_tema,
        'uuid_referencia' => 'test-uuid-detail',
        'estado' => 'COMPLETADO',
        'titulo' => 'Transcripción Detalle',
        'texto_plano' => 'Contenido completo',
    ]);

    $response = $this->getJson("/api/transcripciones/{$transcripcion->id_transcripcion}", authHeaders($usuario));

    $response->assertStatus(200)
        ->assertJson(['titulo' => 'Transcripción Detalle']);
});

test('ver transcripción inexistente retorna 404', function () {
    $usuario = Usuario::factory()->create();

    $response = $this->getJson('/api/transcripciones/999', authHeaders($usuario));

    $response->assertStatus(404);
});
