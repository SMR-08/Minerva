<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AsignaturaController;
use App\Http\Controllers\TemaController;
use App\Http\Controllers\ProcesamientoAudioController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\SseController;

// Public Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Asignaturas CRUD
    Route::apiResource('asignaturas', AsignaturaController::class);

    // Temas CRUD
    Route::apiResource('temas', TemaController::class);
    
    // Etiquetas CRUD
    Route::apiResource('tags', TagController::class);
    
    // Procesamiento de Audio e IA
    Route::get('ia/estado', [ProcesamientoAudioController::class, 'verificarEstado']);
    Route::get('transcripciones', [ProcesamientoAudioController::class, 'index']);
    Route::post('temas/{id}/procesar-audio', [ProcesamientoAudioController::class, 'procesarAudio']);
    Route::get('transcripciones/{id}', [ProcesamientoAudioController::class, 'show']);

    // SSE - Actualizaciones en tiempo real
    Route::get('transcripciones/{uuid}/estado', [SseController::class, 'estado']);

    // Callbacks de IA (protegido con secret)
    Route::post('ia/callback', [ProcesamientoAudioController::class, 'procesarCallback'])->name('ia.callback');
    Route::post('ia/sse-update', [SseController::class, 'sseUpdate']);
    // Rutas de Administración
    Route::middleware('es_admin')->prefix('admin')->group(function () {
        Route::apiResource('usuarios', \App\Http\Controllers\Admin\UsuarioController::class);
    });
});
