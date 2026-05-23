<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AsignaturaController;
use App\Http\Controllers\TemaController;
use App\Http\Controllers\ProcesamientoAudioController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\SseController;

// Rutas públicas
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:30,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:30,1');

// Callbacks de IA (autenticados con secret propio, no con Sanctum)
Route::post('ia/callback', [ProcesamientoAudioController::class, 'procesarCallback'])->name('ia.callback')->middleware('throttle:30,1');
Route::post('ia/sse-update', [SseController::class, 'sseUpdate'])->middleware('throttle:30,1');

// SSE público (autenticación vía query param para EventSource)
Route::get('transcripciones/{uuid}/estado', [SseController::class, 'estado'])->middleware('throttle:60,1');

// Rutas protegidas
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Token temporal para SSE (un solo uso, 30s TTL)
    Route::post('/sse/token', [SseController::class, 'generarTokenSSE']);

    // CRUD de Asignaturas
    Route::apiResource('asignaturas', AsignaturaController::class);

    // CRUD de Temas
    Route::apiResource('temas', TemaController::class);
    
    // CRUD de Etiquetas (solo index, store, destroy)
    Route::apiResource('tags', TagController::class)->only(['index', 'store', 'destroy']);
    
    // Procesamiento de Audio e IA
    Route::get('ia/estado', [ProcesamientoAudioController::class, 'verificarEstado']);
    Route::get('transcripciones', [ProcesamientoAudioController::class, 'index']);
    Route::post('temas/{id}/procesar-audio', [ProcesamientoAudioController::class, 'procesarAudio']);
    Route::get('transcripciones/{id}', [ProcesamientoAudioController::class, 'show']);
    Route::put('transcripciones/{id}', [ProcesamientoAudioController::class, 'update']);
    Route::delete('transcripciones/{id}', [ProcesamientoAudioController::class, 'destroy']);

    // Rutas de Administración
    Route::middleware('es_admin')->prefix('admin')->group(function () {
        Route::apiResource('usuarios', \App\Http\Controllers\Admin\UsuarioController::class)->only(['store', 'update', 'destroy']);
    });
});
