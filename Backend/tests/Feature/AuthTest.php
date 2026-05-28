<?php

use App\Models\Usuario;

/*
|--------------------------------------------------------------------------
| Auth Feature Tests
|--------------------------------------------------------------------------
*/

// ==================== REGISTRO ====================

test('registro exitoso con datos válidos', function () {
    $response = $this->postJson('/api/register', [
        'nombre_completo' => 'Juan Pérez',
        'email' => 'juan@prueba.com',
        'password' => 'password123',
        'device_name' => 'test-browser',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'token',
            'usuario' => ['id_usuario', 'nombre', 'email', 'id_rol'],
        ])
        ->assertJson([
            'message' => 'Usuario registrado correctamente',
            'usuario' => [
                'nombre' => 'Juan Pérez',
                'email' => 'juan@prueba.com',
            ],
        ]);

    $this->assertDatabaseHas('usuarios', [
        'email' => 'juan@prueba.com',
        'nombre_completo' => 'Juan Pérez',
    ]);
});

test('registro falla con email duplicado', function () {
    Usuario::factory()->create([
        'email' => 'duplicado@prueba.com',
    ]);

    $response = $this->postJson('/api/register', [
        'nombre_completo' => 'Otro Usuario',
        'email' => 'duplicado@prueba.com',
        'password' => 'password123',
        'device_name' => 'test-browser',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('registro falla con campos vacíos', function () {
    $response = $this->postJson('/api/register', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['nombre_completo', 'email', 'password']);
});

test('registro falla con email inválido', function () {
    $response = $this->postJson('/api/register', [
        'nombre_completo' => 'Juan Pérez',
        'email' => 'no-es-email',
        'password' => 'password123',
        'device_name' => 'test-browser',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('registro falla con contraseña corta', function () {
    $response = $this->postJson('/api/register', [
        'nombre_completo' => 'Juan Pérez',
        'email' => 'juan@prueba.com',
        'password' => '123',
        'device_name' => 'test-browser',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('registro genera token Sanctum', function () {
    $response = $this->postJson('/api/register', [
        'nombre_completo' => 'Juan Pérez',
        'email' => 'juan@prueba.com',
        'password' => 'password123',
        'device_name' => 'test-browser',
    ]);

    $response->assertStatus(201);
    expect($response->json('token'))->toBeString();
    expect($response->json('token'))->not()->toBeEmpty();
});

// ==================== LOGIN ====================

test('login exitoso con credenciales válidas', function () {
    Usuario::factory()->create([
        'email' => 'login@prueba.com',
        'password_hash' => bcrypt('password123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'login@prueba.com',
        'password' => 'password123',
        'device_name' => 'test-browser',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'token',
            'usuario' => ['id_usuario', 'nombre', 'email', 'id_rol'],
        ]);
});

test('login falla con credenciales incorrectas', function () {
    Usuario::factory()->create([
        'email' => 'login@prueba.com',
        'password_hash' => bcrypt('password123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'login@prueba.com',
        'password' => 'wrong-password',
        'device_name' => 'test-browser',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('login falla con email inexistente', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'noexiste@prueba.com',
        'password' => 'password123',
        'device_name' => 'test-browser',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('login falla con usuario inactivo', function () {
    Usuario::factory()->create([
        'email' => 'inactivo@prueba.com',
        'password_hash' => bcrypt('password123'),
        'id_estado' => 2, // INACTIVO
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'inactivo@prueba.com',
        'password' => 'password123',
        'device_name' => 'test-browser',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('login falla con campos vacíos', function () {
    $response = $this->postJson('/api/login', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

// ==================== LOGOUT ====================

test('logout cierra sesión correctamente', function () {
    $usuario = Usuario::factory()->create([
        'email' => 'logout@prueba.com',
        'password_hash' => bcrypt('password123'),
    ]);

    $token = $usuario->createToken('test-token')->plainTextToken;

    $response = $this->postJson('/api/logout', [], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertStatus(200)
        ->assertJson(['message' => 'Sesión cerrada correctamente']);

    // Verify token was deleted
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('logout falla sin autenticación', function () {
    $response = $this->postJson('/api/logout');

    $response->assertStatus(401);
});

// ==================== USER INFO ====================

test('obtener información del usuario autenticado', function () {
    $usuario = Usuario::factory()->create([
        'email' => 'info@prueba.com',
        'nombre_completo' => 'Info User',
    ]);

    $token = $usuario->createToken('test-token')->plainTextToken;

    $response = $this->getJson('/api/user', [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'email' => 'info@prueba.com',
            'nombre' => 'Info User',
        ]);
});

test('obtener usuario falla sin autenticación', function () {
    $response = $this->getJson('/api/user');

    $response->assertStatus(401);
});

// ==================== AJUSTES DE USUARIO ====================

test('usuario puede actualizar su perfil', function () {
    $usuario = Usuario::factory()->create([
        'email' => 'perfil@prueba.com',
        'nombre_completo' => 'Nombre Original',
    ]);

    $response = $this->patchJson('/api/user/profile', [
        'nombre_completo' => 'Nombre Actualizado',
        'email' => 'actualizado@prueba.com',
    ], authHeaders($usuario));

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Perfil actualizado correctamente',
            'usuario' => [
                'nombre' => 'Nombre Actualizado',
                'email' => 'actualizado@prueba.com',
            ],
        ]);

    $this->assertDatabaseHas('usuarios', [
        'id_usuario' => $usuario->id_usuario,
        'nombre_completo' => 'Nombre Actualizado',
        'email' => 'actualizado@prueba.com',
    ]);
});

test('actualizar perfil valida email único excepto el propio', function () {
    Usuario::factory()->create(['email' => 'ocupado@prueba.com']);
    $usuario = Usuario::factory()->create(['email' => 'propio@prueba.com']);

    $response = $this->patchJson('/api/user/profile', [
        'nombre_completo' => 'Nombre Actualizado',
        'email' => 'ocupado@prueba.com',
    ], authHeaders($usuario));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    $response = $this->patchJson('/api/user/profile', [
        'nombre_completo' => 'Nombre Actualizado',
        'email' => 'propio@prueba.com',
    ], authHeaders($usuario));

    $response->assertStatus(200)
        ->assertJsonPath('usuario.email', 'propio@prueba.com');
});

test('usuario puede cambiar su contraseña', function () {
    $usuario = Usuario::factory()->create([
        'password_hash' => bcrypt('password-actual'),
    ]);

    $response = $this->patchJson('/api/user/password', [
        'password_actual' => 'password-actual',
        'password_nuevo' => 'password-nuevo',
        'password_nuevo_confirmation' => 'password-nuevo',
    ], authHeaders($usuario));

    $response->assertStatus(200)
        ->assertJson(['message' => 'Contraseña actualizada correctamente']);

    $usuario->refresh();
    expect(\Illuminate\Support\Facades\Hash::check('password-nuevo', $usuario->password_hash))->toBeTrue();
});

test('cambiar contraseña falla si la contraseña actual no coincide', function () {
    $usuario = Usuario::factory()->create([
        'password_hash' => bcrypt('password-actual'),
    ]);

    $response = $this->patchJson('/api/user/password', [
        'password_actual' => 'incorrecta',
        'password_nuevo' => 'password-nuevo',
        'password_nuevo_confirmation' => 'password-nuevo',
    ], authHeaders($usuario));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password_actual']);
});

test('usuario puede eliminar su cuenta', function () {
    $usuario = Usuario::factory()->create([
        'email' => 'borrar@prueba.com',
    ]);
    $token = $usuario->createToken('test-token')->plainTextToken;

    $response = $this->deleteJson('/api/user', [], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertStatus(200)
        ->assertJson(['message' => 'Cuenta eliminada correctamente']);

    $this->assertDatabaseMissing('usuarios', [
        'id_usuario' => $usuario->id_usuario,
    ]);
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('rutas de ajustes requieren autenticación', function () {
    $this->patchJson('/api/user/profile')->assertStatus(401);
    $this->patchJson('/api/user/password')->assertStatus(401);
    $this->deleteJson('/api/user')->assertStatus(401);
});
