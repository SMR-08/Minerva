<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\UsuarioController as AdminUsuarioController;

Route::get('/', function () {
    return view('welcome');
});

// Admin Authentication Routes (Public)
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login.post');
});

// Admin Panel Routes (Protected)
Route::prefix('admin')->name('admin.')->middleware(['auth:web', 'es_admin'])->group(function () {
    // Logout
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
    
    // Dashboard
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    
    // Debug Console
    Route::get('/debug', [AdminDashboardController::class, 'debug'])->name('debug');
    Route::post('/debug/test-ia', [AdminDashboardController::class, 'testIA'])->name('debug.test-ia');
    Route::post('/debug/upload-audio', [AdminDashboardController::class, 'uploadAudio'])->name('debug.upload-audio');
    
    // Gestión de Usuarios
    Route::get('/usuarios', [AdminUsuarioController::class, 'indexView'])->name('usuarios.index');
    Route::post('/usuarios', [AdminUsuarioController::class, 'store'])->name('usuarios.store');
    Route::put('/usuarios/{id}', [AdminUsuarioController::class, 'update'])->name('usuarios.update');
    Route::delete('/usuarios/{id}', [AdminUsuarioController::class, 'destroy'])->name('usuarios.destroy');
});

