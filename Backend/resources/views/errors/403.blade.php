<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Acceso Denegado</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .glass { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.1); }
    </style>
</head>
<body class="h-full text-slate-200 antialiased bg-[radial-gradient(circle_at_top_right,_var(--tw-gradient-stops))] from-slate-900 via-slate-950 to-slate-950">
    <div class="flex min-h-full items-center justify-center px-4 py-12 sm:px-6 lg:px-8">
        <div class="w-full max-w-lg text-center space-y-8">
            <!-- Icon -->
            <div class="flex justify-center">
                <div class="w-24 h-24 rounded-3xl bg-red-500/10 border border-red-500/20 flex items-center justify-center">
                    <svg class="w-12 h-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
            </div>

            <!-- Content -->
            <div class="glass rounded-[2rem] p-10 shadow-2xl">
                <h1 class="text-6xl font-black text-white mb-4">403</h1>
                <h2 class="text-2xl font-bold text-white mb-4">Acceso Denegado</h2>
                <p class="text-slate-400 mb-8">
                    No tienes permisos de administrador para acceder a esta sección del sistema.
                </p>

                <div class="space-y-3">
                    @auth
                        <a href="{{ route('admin.logout') }}" 
                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                           class="inline-block w-full py-3 px-6 bg-slate-800 hover:bg-slate-700 text-white font-semibold rounded-xl transition-all">
                            Cerrar Sesión
                        </a>
                        <form id="logout-form" action="{{ route('admin.logout') }}" method="POST" class="hidden">
                            @csrf
                        </form>
                    @else
                        <a href="{{ route('admin.login') }}" 
                           class="inline-block w-full py-3 px-6 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition-all">
                            Ir al Login
                        </a>
                    @endauth
                </div>
            </div>

            <!-- Footer -->
            <p class="text-xs text-slate-500">
                Si crees que esto es un error, contacta al administrador del sistema.
            </p>
        </div>
    </div>
</body>
</html>
