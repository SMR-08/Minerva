<?php

use App\Models\Asignatura;
use App\Models\Usuario;

/*
|--------------------------------------------------------------------------
| Asignatura Feature Tests
|--------------------------------------------------------------------------
*/

// ==================== HELPERS ====================


// ==================== LISTAR ====================

test('usuario puede listar sus asignaturas', function () {
    $usuario = Usuario::factory()->create();

    Asignatura::create([
        'id_usuario' => $usuario->id_usuario,
        'nombre' => 'Programación',
        'profesor' => 'Dr. García',
        'descripcion' => 'Asignatura de programación',
        'color_hex' => '#3B82F6',
    ]);

    $response = $this->getJson('/api/asignaturas', authHeaders($usuario));

    $response->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonFragment(['nombre' => 'Programación']);
});

test('usuario solo ve sus propias asignaturas', function () {
    $usuario1 = Usuario::factory()->create();
    $usuario2 = Usuario::factory()->create();

    Asignatura::create([
        'id_usuario' => $usuario1->id_usuario,
        'nombre' => 'Asignatura Usuario 1',
    ]);

    Asignatura::create([
        'id_usuario' => $usuario2->id_usuario,
        'nombre' => 'Asignatura Usuario 2',
    ]);

    $response = $this->getJson('/api/asignaturas', authHeaders($usuario1));

    $response->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonFragment(['nombre' => 'Asignatura Usuario 1']);

    // Verify usuario2's asignatura is NOT in the response
    $response->assertJsonMissing(['nombre' => 'Asignatura Usuario 2']);
});

test('listar asignaturas requiere autenticación', function () {
    $response = $this->getJson('/api/asignaturas');

    $response->assertStatus(401);
});

// ==================== CREAR ====================

test('usuario puede crear una asignatura', function () {
    $usuario = Usuario::factory()->create();

    $response = $this->postJson('/api/asignaturas', [
        'nombre' => 'Matemáticas',
        'profesor' => 'Dr. López',
        'descripcion' => 'Cálculo y álgebra',
        'color_hex' => '#EF4444',
    ], authHeaders($usuario));

    $response->assertStatus(201)
        ->assertJson([
            'nombre' => 'Matemáticas',
            'profesor' => 'Dr. López',
        ]);

    $this->assertDatabaseHas('asignaturas', [
        'id_usuario' => $usuario->id_usuario,
        'nombre' => 'Matemáticas',
    ]);
});

test('crear asignatura requiere nombre', function () {
    $usuario = Usuario::factory()->create();

    $response = $this->postJson('/api/asignaturas', [
        'profesor' => 'Dr. López',
    ], authHeaders($usuario));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['nombre']);
});

test('crear asignatura requiere autenticación', function () {
    $response = $this->postJson('/api/asignaturas', [
        'nombre' => 'Matemáticas',
    ]);

    $response->assertStatus(401);
});

// ==================== VER ====================

test('usuario puede ver una asignatura propia', function () {
    $usuario = Usuario::factory()->create();

    $asignatura = Asignatura::create([
        'id_usuario' => $usuario->id_usuario,
        'nombre' => 'Física',
    ]);

    $response = $this->getJson("/api/asignaturas/{$asignatura->id_asignatura}", authHeaders($usuario));

    $response->assertStatus(200)
        ->assertJson(['nombre' => 'Física']);
});

test('usuario no puede ver asignatura de otro usuario', function () {
    $usuario1 = Usuario::factory()->create();
    $usuario2 = Usuario::factory()->create();

    $asignatura = Asignatura::create([
        'id_usuario' => $usuario1->id_usuario,
        'nombre' => 'Asignatura Privada',
    ]);

    $response = $this->getJson("/api/asignaturas/{$asignatura->id_asignatura}", authHeaders($usuario2));

    $response->assertStatus(404);
});

test('ver asignatura inexistente retorna 404', function () {
    $usuario = Usuario::factory()->create();

    $response = $this->getJson('/api/asignaturas/999', authHeaders($usuario));

    $response->assertStatus(404);
});

// ==================== ACTUALIZAR ====================

test('usuario puede actualizar su asignatura', function () {
    $usuario = Usuario::factory()->create();

    $asignatura = Asignatura::create([
        'id_usuario' => $usuario->id_usuario,
        'nombre' => 'Nombre Original',
    ]);

    $response = $this->putJson("/api/asignaturas/{$asignatura->id_asignatura}", [
        'nombre' => 'Nombre Actualizado',
        'profesor' => 'Nuevo Profesor',
    ], authHeaders($usuario));

    $response->assertStatus(200)
        ->assertJson(['nombre' => 'Nombre Actualizado']);

    $this->assertDatabaseHas('asignaturas', [
        'id_asignatura' => $asignatura->id_asignatura,
        'nombre' => 'Nombre Actualizado',
    ]);
});

test('usuario no puede actualizar asignatura de otro', function () {
    $usuario1 = Usuario::factory()->create();
    $usuario2 = Usuario::factory()->create();

    $asignatura = Asignatura::create([
        'id_usuario' => $usuario1->id_usuario,
        'nombre' => 'Asignatura Ajena',
    ]);

    $response = $this->putJson("/api/asignaturas/{$asignatura->id_asignatura}", [
        'nombre' => 'Hackeado',
    ], authHeaders($usuario2));

    $response->assertStatus(404);
});

// ==================== ELIMINAR ====================

test('usuario puede eliminar su asignatura', function () {
    $usuario = Usuario::factory()->create();

    $asignatura = Asignatura::create([
        'id_usuario' => $usuario->id_usuario,
        'nombre' => 'Para Eliminar',
    ]);

    $response = $this->deleteJson("/api/asignaturas/{$asignatura->id_asignatura}", [], authHeaders($usuario));

    $response->assertStatus(200)
        ->assertJson(['message' => 'Asignatura eliminada correctamente']);

    $this->assertDatabaseMissing('asignaturas', [
        'id_asignatura' => $asignatura->id_asignatura,
    ]);
});

test('usuario no puede eliminar asignatura de otro', function () {
    $usuario1 = Usuario::factory()->create();
    $usuario2 = Usuario::factory()->create();

    $asignatura = Asignatura::create([
        'id_usuario' => $usuario1->id_usuario,
        'nombre' => 'Asignatura Protegida',
    ]);

    $response = $this->deleteJson("/api/asignaturas/{$asignatura->id_asignatura}", [], authHeaders($usuario2));

    $response->assertStatus(404);
});
