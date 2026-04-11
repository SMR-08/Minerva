<?php

namespace Database\Factories;

use App\Models\Rol;
use App\Models\Estado;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Usuario>
 */
class UsuarioFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'nombre_completo' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => static::$password ??= Hash::make('password'),
            'id_rol' => 2, // USUARIO
            'id_estado' => 1, // ACTIVO
            'ultimo_acceso' => now(),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'id_rol' => 1,
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'id_estado' => 2,
        ]);
    }
}
