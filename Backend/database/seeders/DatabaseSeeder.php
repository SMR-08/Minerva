<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Usuario;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Roles and Estados are already inserted via Migration (insertOrIgnore)
        
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
