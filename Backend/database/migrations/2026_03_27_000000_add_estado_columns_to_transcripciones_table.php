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
        Schema::table('transcripciones', function (Blueprint $table) {
            // Estado del procesamiento: SUBIENDO, ENCOLADO, PROCESANDO, COMPLETADO, FALLIDO
            $table->enum('estado', ['SUBIENDO', 'ENCOLADO', 'PROCESANDO', 'COMPLETADO', 'FALLIDO'])
                  ->default('SUBIENDO')
                  ->after('uuid_referencia')
                  ->comment('Estado actual del procesamiento');

            // Progreso porcentual (0-100)
            $table->tinyInteger('progreso_porcentaje')
                  ->default(0)
                  ->after('estado')
                  ->comment('Progreso actual del procesamiento (0-100)');

            // Etapa actual del procesamiento
            $table->string('etapa_actual', 50)
                  ->nullable()
                  ->after('progreso_porcentaje')
                  ->comment('Etapa actual: ASR, DIARIZACION, ALINEACION, POST_PROCESADO');

            // Intentos de procesamiento
            $table->smallInteger('intentos')
                  ->default(0)
                  ->after('etapa_actual')
                  ->comment('Número de intentos de procesamiento');

            // Mensaje de error (si falló)
            $table->text('error_mensaje')
                  ->nullable()
                  ->after('intentos')
                  ->comment('Mensaje de error si el procesamiento falló');

            // Índice para consultas por estado
            $table->index('estado', 'idx_transcripciones_estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transcripciones', function (Blueprint $table) {
            $table->dropIndex('idx_transcripciones_estado');
            $table->dropColumn([
                'estado',
                'progreso_porcentaje',
                'etapa_actual',
                'intentos',
                'error_mensaje'
            ]);
        });
    }
};
