<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Añade SoftDeletes a las tablas principales de Minerva
     * para evitar pérdida de datos por DELETE accidental.
     */
    public function up(): void
    {
        Schema::table('asignaturas', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('temas', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('transcripciones', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('asignaturas', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('temas', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('transcripciones', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
