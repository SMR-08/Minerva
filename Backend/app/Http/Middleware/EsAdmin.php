<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar si el usuario está autenticado y es admin
        if ($request->user() && $request->user()->id_rol === 1) {
            return $next($request);
        }

        // Si es una petición API, devolver JSON
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Acceso denegado. Se requieren permisos de administrador.'
            ], 403);
        }

        // Si es petición web, mostrar página 403
        abort(403, 'No tienes permisos de administrador.');
    }
}
