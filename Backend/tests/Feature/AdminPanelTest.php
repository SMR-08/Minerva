<?php

use App\Models\Usuario;
use App\Models\Rol;
use App\Models\EstadoUsuario;

/*
|--------------------------------------------------------------------------
| Admin Panel Feature Tests
|--------------------------------------------------------------------------
*/

// ==================== HELPERS ====================

function loginComoAdmin(): Usuario
{
    $admin = Usuario::factory()->create([
        'id_rol' => 1, // ADMIN
        'id_estado' => 1, // ACTIVO
    ]);
    return $admin;
}

function loginComoUsuario(): Usuario
{
    return Usuario::factory()->create([
        'id_rol' => 2, // USUARIO
        'id_estado' => 1,
    ]);
}

beforeEach(function () {
    $this->withoutVite();
});

// ==================== LOGIN ====================

test('admin login page loads', function () {
    $response = $this->get('/admin/login');
    $response->assertStatus(200);
});

test('admin can login with valid credentials', function () {
    $admin = loginComoAdmin();

    $response = $this->post('/admin/login', [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('/admin');
});

test('non-admin cannot access admin panel', function () {
    $user = loginComoUsuario();

    $response = $this->actingAs($user, 'web')->get('/admin/');
    $response->assertStatus(403);
});

// ==================== DASHBOARD ====================

test('admin dashboard loads with real stats', function () {
    $admin = loginComoAdmin();

    $response = $this->actingAs($admin, 'web')->get('/admin/');

    $response->assertStatus(200);
    $response->assertSee('Usuarios Totales');
    $response->assertSee('Transcripciones');
    $response->assertSee('Estado del Sistema');
});

// ==================== DEBUG CONSOLE ====================

test('admin debug console loads', function () {
    $admin = loginComoAdmin();

    $response = $this->actingAs($admin, 'web')->get('/admin/debug');
    $response->assertStatus(200);
    $response->assertSee('Estado del Servicio');
});

// ==================== GESTIÓN DE USUARIOS ====================

test('admin can view users list', function () {
    $admin = loginComoAdmin();

    $response = $this->actingAs($admin, 'web')->get('/admin/usuarios');

    $response->assertStatus(200);
    $response->assertSee($admin->email);
});

test('admin can create a new user', function () {
    $admin = loginComoAdmin();

    $response = $this->actingAs($admin, 'web')->post('/admin/usuarios', [
        'nombre_completo' => 'Nuevo Usuario Test',
        'email' => 'nuevo@test.com',
        'password' => 'password123',
        'id_rol' => 2,
        'id_estado' => 1,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('usuarios', ['email' => 'nuevo@test.com']);
});

test('admin can update a user', function () {
    $admin = loginComoAdmin();
    $user = loginComoUsuario();

    $response = $this->actingAs($admin, 'web')->put("/admin/usuarios/{$user->id_usuario}", [
        'nombre_completo' => 'Nombre Actualizado',
        'email' => $user->email,
        'id_rol' => 2,
        'id_estado' => 1,
    ]);

    $response->assertRedirect();
    $user->refresh();
    expect($user->nombre_completo)->toBe('Nombre Actualizado');
});

test('admin can delete a user', function () {
    $admin = loginComoAdmin();
    $user = loginComoUsuario();

    $response = $this->actingAs($admin, 'web')->delete("/admin/usuarios/{$user->id_usuario}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('usuarios', ['id_usuario' => $user->id_usuario]);
});
