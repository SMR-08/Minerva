<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Rol;
use App\Models\EstadoUsuario;
use App\Models\Usuario;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Rol::firstOrCreate(
            ['id_rol' => 1],
            ['nombre' => 'ADMIN', 'descripcion' => 'Administrador del sistema']
        );
        Rol::firstOrCreate(
            ['id_rol' => 2],
            ['nombre' => 'USUARIO', 'descripcion' => 'Usuario estándar']
        );

        EstadoUsuario::firstOrCreate(
            ['id_estado' => 1],
            ['nombre' => 'ACTIVO']
        );
        EstadoUsuario::firstOrCreate(
            ['id_estado' => 2],
            ['nombre' => 'SUSPENDIDO']
        );
        EstadoUsuario::firstOrCreate(
            ['id_estado' => 3],
            ['nombre' => 'BANEADO']
        );
        
        // Admin User
        if (!Usuario::where('email', 'admin@minerva.com')->exists()) {
            Usuario::create([
                'id_rol' => 1, // ADMIN
                'id_estado' => 1, // ACTIVO
                'email' => 'admin@minerva.com',
                'password_hash' => Hash::make('admin123'),
                'nombre_completo' => 'Administrador Minerva',
            ]);
            $this->command->info('Usuario Admin creado: admin@minerva.com / admin123');
        }
    }
}
