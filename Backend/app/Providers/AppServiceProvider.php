<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Las respuestas JSON de la API no usan envoltura "data"
        // para mantener compatibilidad con el frontend y tests existentes.
        JsonResource::withoutWrapping();
    }
}
