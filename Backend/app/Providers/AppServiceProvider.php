<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;
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

        // Forzar HTTPS cuando estamos detrás de un proxy (ALB) o APP_URL es https.
        // Esto asegura que Vite, redirects y URLs generadas usen https.
        if (
            config('app.env') === 'production'
            || str_starts_with(config('app.url'), 'https')
            || request()->header('X-Forwarded-Proto') === 'https'
        ) {
            URL::forceScheme('https');
            // Forzar el root URL sin puerto (el ALB expone 443, no 9122)
            URL::forceRootUrl(config('app.url'));
        }
    }
}
