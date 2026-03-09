<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\UsuarioController as AdminUsuarioController;

Route::get('/', function () {
    return view('welcome');
});

// Admin Panel Routes (Web)
// Nota: Deberías aplicar un middleware 'auth' y 'es_admin' aquí en producción
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/debug', [AdminDashboardController::class, 'debug'])->name('debug');
    Route::post('/debug/test-ia', [AdminDashboardController::class, 'testIA'])->name('debug.test-ia');
    Route::post('/debug/upload-audio', [AdminDashboardController::class, 'uploadAudio'])->name('debug.upload-audio');
    
    // Gestión de Usuarios (Vistas y Acciones)
    Route::get('/usuarios', [AdminUsuarioController::class, 'indexView'])->name('usuarios.index');
    Route::post('/usuarios', [AdminUsuarioController::class, 'store'])->name('usuarios.store');
    Route::put('/usuarios/{id}', [AdminUsuarioController::class, 'update'])->name('usuarios.update');
    Route::delete('/usuarios/{id}', [AdminUsuarioController::class, 'destroy'])->name('usuarios.destroy');
});

