<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Roles
        Schema::create('roles', function (Blueprint $table) {
            $table->id('id_rol');
            $table->string('nombre', 50)->unique();
            $table->string('descripcion', 255)->nullable();
        });

        // Insert Default Roles
        DB::table('roles')->insertOrIgnore([
            ['id_rol' => 1, 'nombre' => 'ADMIN', 'descripcion' => 'Administrador del sistema'],
            ['id_rol' => 2, 'nombre' => 'USUARIO', 'descripcion' => 'Usuario estándar'],
        ]);

        // 2. Estados Usuario
        Schema::create('estados_usuario', function (Blueprint $table) {
            $table->id('id_estado');
            $table->string('nombre', 50)->unique();
        });

        // Insert Default Estados
        DB::table('estados_usuario')->insertOrIgnore([
            ['id_estado' => 1, 'nombre' => 'ACTIVO'],
            ['id_estado' => 2, 'nombre' => 'SUSPENDIDO'],
            ['id_estado' => 3, 'nombre' => 'BANEADO'],
        ]);

        // 3. Usuarios
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id('id_usuario');
            $table->foreignId('id_rol')->default(2)->constrained('roles', 'id_rol');
            $table->foreignId('id_estado')->default(1)->constrained('estados_usuario', 'id_estado');
            $table->string('email', 100)->unique();
            $table->string('password_hash', 255);
            $table->string('nombre_completo', 100);
            $table->timestamp('fecha_registro')->useCurrent();
            $table->timestamp('ultimo_acceso')->nullable();
        });

        // 4. Historial Accesos
        Schema::create('historial_accesos', function (Blueprint $table) {
            $table->id('id_acceso');
            $table->foreignId('id_usuario')->constrained('usuarios', 'id_usuario')->onDelete('cascade');
            $table->timestamp('fecha_acceso')->useCurrent();
            $table->string('ip_acceso', 45)->nullable();
            $table->text('user_agent')->nullable();
        });

        // 5. Asignaturas (Updated with 'profesor')
        Schema::create('asignaturas', function (Blueprint $table) {
            $table->id('id_asignatura');
            $table->foreignId('id_usuario')->constrained('usuarios', 'id_usuario')->onDelete('cascade');
            $table->string('nombre', 100);
            $table->string('profesor', 100)->nullable()->comment('Nombre del profesor asignado'); // Nueva columna solicitada
            $table->text('descripcion')->nullable();
            $table->string('color_hex', 7)->default('#3B82F6');
            $table->timestamp('fecha_creacion')->useCurrent();
        });

        // 6. Temas
        Schema::create('temas', function (Blueprint $table) {
            $table->id('id_tema');
            $table->foreignId('id_asignatura')->constrained('asignaturas', 'id_asignatura')->onDelete('cascade');
            $table->string('nombre', 100);
            $table->integer('orden')->default(0);
        });

        // 7. Tags
        Schema::create('tags', function (Blueprint $table) {
            $table->id('id_tag');
            $table->foreignId('id_usuario')->constrained('usuarios', 'id_usuario')->onDelete('cascade');
            $table->string('nombre', 50);
            $table->string('color_hex', 7)->default('#6B7280');
            $table->unique(['id_usuario', 'nombre']);
        });

        // 8. Transcripciones
        Schema::create('transcripciones', function (Blueprint $table) {
            $table->id('id_transcripcion');
            $table->foreignId('id_tema')->constrained('temas', 'id_tema')->onDelete('cascade');
            $table->string('uuid_referencia', 64)->unique()->comment('UUID para carpeta fisica');
            
            // Metadatos Audio
            $table->string('nombre_archivo_original', 255)->nullable();
            $table->float('duracion_segundos')->nullable();
            $table->timestamp('fecha_grabacion')->useCurrent();
            
            // Contenido IA
            $table->string('titulo', 200)->default('Nota sin título');
            $table->longText('texto_plano')->nullable();
            $table->longText('texto_diarizado')->nullable(); // JSON o Texto estructurado
            $table->text('resumen_ia')->nullable();
            $table->text('mapa_mental_mermaid')->nullable();
            
            $table->timestamp('fecha_procesamiento')->useCurrent();
        });

        // 9. Transcripciones Tags (Pivot)
        Schema::create('transcripciones_tags', function (Blueprint $table) {
            $table->foreignId('id_transcripcion')->constrained('transcripciones', 'id_transcripcion')->onDelete('cascade');
            $table->foreignId('id_tag')->constrained('tags', 'id_tag')->onDelete('cascade');
            $table->primary(['id_transcripcion', 'id_tag']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transcripciones_tags');
        Schema::dropIfExists('transcripciones');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('temas');
        Schema::dropIfExists('asignaturas');
        Schema::dropIfExists('historial_accesos');
        Schema::dropIfExists('usuarios');
        Schema::dropIfExists('estados_usuario');
        Schema::dropIfExists('roles');
    }
};
