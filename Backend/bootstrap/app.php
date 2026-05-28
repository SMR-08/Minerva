<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',

        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'es_admin' => \App\Http\Middleware\EsAdmin::class,
        ]);

        // Confiar en proxies (ALB de AWS envía X-Forwarded-*)
        $middleware->trustProxies(at: '*');
        
        // Redirect unauthenticated users to admin login (solo para rutas web, no API)
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('api/*')) {
                return null; // No redirigir peticiones API, dejar que devuelvan 401
            }
            return route('admin.login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
