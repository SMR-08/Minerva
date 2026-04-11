<?php

use App\Models\Asignatura;
use App\Models\Tema;
use App\Models\Usuario;

/*
|--------------------------------------------------------------------------
| Tema Feature Tests
|--------------------------------------------------------------------------
*/

// ==================== HELPERS ====================



// ==================== LISTAR ====================

test('usuario puede listar temas de una asignatura propia', function () {
    $usuario = Usuario::factory()->create();
    $asignatura = createAsignaturaPara($usuario);

    Tema::create([
        'id_asignatura' => $asignatura->id_asignatura,
        'nombre' => 'Tema 1',
        'orden' => 0,
    ]);

    Tema::create([
        'id_asignatura' => $asignatura->id_asignatura,
        'nombre' => 'Tema 2',
        'orden' => 1,
    ]);

    // GET /api/temas?asignatura_id=X
    $response = $this->getJson("/api/temas?asignatura_id={$asignatura->id_asignatura}", authHeaders($usuario));

    $response->assertStatus(200)
        ->assertJsonCount(2);
});

test('listar temas requiere autenticación', function () {
    $usuario = Usuario::factory()->create();
    $asignatura = createAsignaturaPara($usuario);

    $response = $this->getJson("/api/temas?asignatura_id={$asignatura->id_asignatura}");

    $response->assertStatus(401);
});

test('usuario no puede listar temas de asignatura ajena', function () {
    $usuario1 = Usuario::factory()->create();
    $usuario2 = Usuario::factory()->create();

    $asignatura = createAsignaturaPara($usuario1);

    $response = $this->getJson("/api/temas?asignatura_id={$asignatura->id_asignatura}", authHeaders($usuario2));

    $response->assertStatus(404);
});

// ==================== CREAR ====================

test('usuario puede crear un tema en su asignatura', function () {
    $usuario = Usuario::factory()->create();
    $asignatura = createAsignaturaPara($usuario);

    $response = $this->postJson('/api/temas', [
        'id_asignatura' => $asignatura->id_asignatura,
        'nombre' => 'Introducción',
    ], authHeaders($usuario));

    $response->assertStatus(201)
        ->assertJson([
            'nombre' => 'Introducción',
            'id_asignatura' => $asignatura->id_asignatura,
        ]);

    $this->assertDatabaseHas('temas', [
        'nombre' => 'Introducción',
        'id_asignatura' => $asignatura->id_asignatura,
    ]);
});

test('crear tema sin orden asigna 0 por defecto', function () {
    $usuario = Usuario::factory()->create();
    $asignatura = createAsignaturaPara($usuario);

    $response = $this->postJson('/api/temas', [
        'id_asignatura' => $asignatura->id_asignatura,
        'nombre' => 'Nuevo tema',
    ], authHeaders($usuario));

    $response->assertStatus(201)
        ->assertJson(['orden' => 0]);
});

test('crear tema requiere nombre', function () {
    $usuario = Usuario::factory()->create();
    $asignatura = createAsignaturaPara($usuario);

    $response = $this->postJson('/api/temas', [
        'id_asignatura' => $asignatura->id_asignatura,
    ], authHeaders($usuario));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['nombre']);
});

test('crear tema requiere autenticación', function () {
    $usuario = Usuario::factory()->create();
    $asignatura = createAsignaturaPara($usuario);

    $response = $this->postJson('/api/temas', [
        'id_asignatura' => $asignatura->id_asignatura,
        'nombre' => 'Tema',
    ]);

    $response->assertStatus(401);
});

test('usuario no puede crear tema en asignatura ajena', function () {
    $usuario1 = Usuario::factory()->create();
    $usuario2 = Usuario::factory()->create();

    $asignatura = createAsignaturaPara($usuario1);

    $response = $this->postJson('/api/temas', [
        'id_asignatura' => $asignatura->id_asignatura,
        'nombre' => 'Tema intruso',
    ], authHeaders($usuario2));

    $response->assertStatus(404);
});

// ==================== VER ====================

test('usuario puede ver un tema de su asignatura', function () {
    $usuario = Usuario::factory()->create();
    $asignatura = createAsignaturaPara($usuario);

    $tema = Tema::create([
        'id_asignatura' => $asignatura->id_asignatura,
        'nombre' => 'Tema visible',
        'orden' => 0,
    ]);

    $response = $this->getJson("/api/temas/{$tema->id_tema}", authHeaders($usuario));

    $response->assertStatus(200)
        ->assertJson(['nombre' => 'Tema visible']);
});

test('usuario no puede ver tema de asignatura ajena', function () {
    $usuario1 = Usuario::factory()->create();
    $usuario2 = Usuario::factory()->create();

    $asignatura = createAsignaturaPara($usuario1);

    $tema = Tema::create([
        'id_asignatura' => $asignatura->id_asignatura,
        'nombre' => 'Tema privado',
        'orden' => 0,
    ]);

    $response = $this->getJson("/api/temas/{$tema->id_tema}", authHeaders($usuario2));

    $response->assertStatus(404);
});

// ==================== ACTUALIZAR ====================

test('usuario puede actualizar un tema propio', function () {
    $usuario = Usuario::factory()->create();
    $asignatura = createAsignaturaPara($usuario);

    $tema = Tema::create([
        'id_asignatura' => $asignatura->id_asignatura,
        'nombre' => 'Nombre original',
        'orden' => 0,
    ]);

    $response = $this->putJson("/api/temas/{$tema->id_tema}", [
        'nombre' => 'Nombre actualizado',
    ], authHeaders($usuario));

    $response->assertStatus(200)
        ->assertJson(['nombre' => 'Nombre actualizado']);
});

test('usuario no puede actualizar tema de asignatura ajena', function () {
    $usuario1 = Usuario::factory()->create();
    $usuario2 = Usuario::factory()->create();

    $asignatura = createAsignaturaPara($usuario1);

    $tema = Tema::create([
        'id_asignatura' => $asignatura->id_asignatura,
        'nombre' => 'Tema protegido',
        'orden' => 0,
    ]);

    $response = $this->putJson("/api/temas/{$tema->id_tema}", [
        'nombre' => 'Hackeado',
    ], authHeaders($usuario2));

    $response->assertStatus(404);
});

// ==================== ELIMINAR ====================

test('usuario puede eliminar un tema propio', function () {
    $usuario = Usuario::factory()->create();
    $asignatura = createAsignaturaPara($usuario);

    $tema = Tema::create([
        'id_asignatura' => $asignatura->id_asignatura,
        'nombre' => 'Para eliminar',
        'orden' => 0,
    ]);

    $response = $this->deleteJson("/api/temas/{$tema->id_tema}", [], authHeaders($usuario));

    $response->assertStatus(200)
        ->assertJson(['message' => 'Tema eliminado correctamente']);

    $this->assertDatabaseMissing('temas', [
        'id_tema' => $tema->id_tema,
    ]);
});

test('usuario no puede eliminar tema de asignatura ajena', function () {
    $usuario1 = Usuario::factory()->create();
    $usuario2 = Usuario::factory()->create();

    $asignatura = createAsignaturaPara($usuario1);

    $tema = Tema::create([
        'id_asignatura' => $asignatura->id_asignatura,
        'nombre' => 'Tema protegido',
        'orden' => 0,
    ]);

    $response = $this->deleteJson("/api/temas/{$tema->id_tema}", [], authHeaders($usuario2));

    $response->assertStatus(404);
});
