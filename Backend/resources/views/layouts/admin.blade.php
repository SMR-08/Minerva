<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Panel Admin - Minerva' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="h-full antialiased" style="background: #F3FAFF; color: #1a1a1a;">
    <div class="flex h-full">
        <!-- Sidebar -->
        <aside class="w-64 flex flex-col border-r" style="background: #F9FCFF; border-color: #E5E7EB;">
            <!-- Logo -->
            <div class="p-6 border-b" style="border-color: #E5E7EB;">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 flex items-center justify-center rounded-lg" style="background: #FFD866;">
                        <span class="font-bold text-lg" style="color: #1a1a1a;">M</span>
                    </div>
                    <div>
                        <span class="text-lg font-bold" style="color: #1a1a1a;">Minerva</span>
                        <span class="text-xs font-semibold ml-1" style="color: #666666;">Admin</span>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-4 py-6 space-y-1">
                <a href="{{ route('admin.dashboard') }}" class="sidebar-item {{ request()->routeIs('admin.dashboard') ? 'sidebar-item-active' : '' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <span class="font-medium text-sm">Dashboard</span>
                </a>
                <a href="{{ route('admin.usuarios.index') }}" class="sidebar-item {{ request()->routeIs('admin.usuarios.*') ? 'sidebar-item-active' : '' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    <span class="font-medium text-sm">Usuarios</span>
                </a>
                
                <div class="pt-6 pb-2 px-4">
                    <p class="text-xs font-semibold" style="color: #999999;">Sistema</p>
                </div>
                
                <a href="{{ route('admin.debug') }}" class="sidebar-item {{ request()->routeIs('admin.debug') ? 'sidebar-item-active' : '' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                    <span class="font-medium text-sm">Debug Console</span>
                </a>
            </nav>

            <!-- User Info & Logout -->
            <div class="p-4 border-t" style="border-color: #E5E7EB;">
                <div class="p-3 rounded-lg flex items-center gap-3 mb-3" style="background: #FFFFFF; border: 1px solid #E5E7EB;">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background: #F3FAFF; border: 1px solid #E5E7EB;">
                        <span class="text-sm font-semibold" style="color: #666666;">{{ strtoupper(substr(auth()->user()->nombre_completo ?? 'Admin', 0, 2)) }}</span>
                    </div>
                    <div class="flex-1 overflow-hidden">
                        <p class="text-sm font-semibold truncate" style="color: #1a1a1a;">{{ auth()->user()->nombre_completo ?? 'Admin' }}</p>
                        <p class="text-xs truncate" style="color: #999999;">Administrador</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="w-full sidebar-item justify-start">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        <span class="font-medium text-sm">Cerrar Sesión</span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- Content Area -->
        <main class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <!-- Header -->
            <header class="h-16 flex items-center justify-between px-8 border-b" style="background: #FFFFFF; border-color: #E5E7EB;">
                <h1 class="text-lg font-semibold" style="color: #1a1a1a;">{{ $header ?? 'Panel de Control' }}</h1>
                <div class="flex items-center gap-3">
                    <span class="text-xs px-3 py-1.5 rounded-lg font-medium" style="background: #D1FAE5; color: #065F46;">Servidor Online</span>
                </div>
            </header>

            <!-- Main Content -->
            <div class="flex-1 overflow-y-auto p-8">
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
