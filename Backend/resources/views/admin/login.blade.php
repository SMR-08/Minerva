<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login Admin - Minerva</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full antialiased" style="background: #F3FAFF; color: #1a1a1a;">
    <div class="flex min-h-full items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <!-- Logo y Header -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-xl mb-4" style="background: #FFD866;">
                    <span class="font-bold text-2xl" style="color: #1a1a1a;">M</span>
                </div>
                <h1 class="text-3xl font-bold mb-2" style="color: #1a1a1a;">
                    Minerva
                </h1>
                <h2 class="text-base font-medium mb-1" style="color: #666666;">
                    Panel de Administración
                </h2>
                <p class="text-sm" style="color: #999999;">
                    Ingresa tus credenciales para acceder
                </p>
            </div>

            <!-- Formulario de Login -->
            <div class="rounded-xl p-8" style="background: #FFFFFF; border: 1px solid #E5E7EB; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);">
                @if (session('status'))
                    <div class="mb-4 p-3 rounded-lg text-sm" style="background: #D1FAE5; border: 1px solid #10B981; color: #065F46;">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-4 p-3 rounded-lg text-sm" style="background: #FEE2E2; border: 1px solid #EF4444; color: #991B1B;">
                        <ul class="space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.login.post') }}" class="space-y-5">
                    @csrf

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium mb-2" style="color: #1a1a1a;">
                            Email
                        </label>
                        <input 
                            id="email" 
                            name="email" 
                            type="email" 
                            autocomplete="email" 
                            required 
                            value="{{ old('email') }}"
                            class="input w-full"
                            placeholder="admin@minerva.com"
                        >
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium mb-2" style="color: #1a1a1a;">
                            Contraseña
                        </label>
                        <input 
                            id="password" 
                            name="password" 
                            type="password" 
                            autocomplete="current-password" 
                            required 
                            class="input w-full"
                            placeholder="••••••••"
                        >
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center">
                        <input 
                            id="remember" 
                            name="remember" 
                            type="checkbox" 
                            class="h-4 w-4 rounded"
                            style="border: 1px solid #E5E7EB; color: #FFD866;"
                        >
                        <label for="remember" class="ml-2 text-sm" style="color: #666666;">
                            Recordar sesión
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <button 
                        type="submit" 
                        class="btn-primary w-full mt-6"
                    >
                        Iniciar Sesión
                    </button>
                </form>
            </div>

            <!-- Footer -->
            <div class="text-center mt-8">
                <p class="text-xs" style="color: #999999;">
                    Minerva © {{ date('Y') }} - Sistema de Gestión Académica
                </p>
            </div>
        </div>
    </div>
</body>
</html>
