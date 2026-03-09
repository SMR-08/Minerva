@extends('layouts.admin')

@section('header', 'Panel de Control')

@section('content')
<div class="space-y-8 animate-fade-in">
    <!-- Welcome Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <h2 class="text-3xl font-bold text-white tracking-tight">Bienvenido de nuevo, <span class="text-blue-500">Admin</span></h2>
            <p class="text-slate-500 mt-1">Aquí tienes el resumen del sistema Minerva.</p>
        </div>
        <div class="flex items-center gap-2 text-xs font-bold text-slate-500 uppercase tracking-widest bg-slate-900/50 px-4 py-2 rounded-xl border border-slate-800">
            <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
            Servidor Activo
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Usuarios -->
        <div class="glass p-8 rounded-[2rem] space-y-4 hover:border-blue-500/30 transition-all group">
            <div class="w-12 h-12 rounded-2xl bg-blue-500/10 flex items-center justify-center text-blue-500 group-hover:scale-110 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.2em]">Usuarios Totales</p>
                <h3 class="text-4xl font-black text-white mt-1">{{ number_format($stats['total_users']) }}</h3>
            </div>
        </div>

        <!-- Transcripciones -->
        <div class="glass p-8 rounded-[2rem] space-y-4 hover:border-purple-500/30 transition-all group">
            <div class="w-12 h-12 rounded-2xl bg-purple-500/10 flex items-center justify-center text-purple-500 group-hover:scale-110 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.2em]">Transcripciones</p>
                <h3 class="text-4xl font-black text-white mt-1">{{ number_format($stats['total_transcriptions']) }}</h3>
            </div>
        </div>

        <!-- Almacenamiento -->
        <div class="glass p-8 rounded-[2rem] space-y-4 hover:border-emerald-500/30 transition-all group">
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 flex items-center justify-center text-emerald-500 group-hover:scale-110 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.2em]">Uso de Disco</p>
                <h3 class="text-4xl font-black text-white mt-1">{{ $stats['storage_used'] }}</h3>
            </div>
        </div>

        <!-- Cola de la IA -->
        <div class="glass p-8 rounded-[2rem] space-y-4 hover:border-amber-500/30 transition-all group">
            <div class="w-12 h-12 rounded-2xl bg-amber-500/10 flex items-center justify-center text-amber-500 group-hover:scale-110 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.2em]">Cola de la IA</p>
                <h3 class="text-4xl font-black text-white mt-1" :class="'{{ $stats['ai_queue'] }}' !== 'N/A' ? 'text-amber-500' : ''">
                    {{ $stats['ai_queue'] }}
                </h3>
            </div>
        </div>
    </div>

    <!-- System Status -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="glass p-10 rounded-[2.5rem] flex flex-col justify-between overflow-hidden relative">
            <div class="relative z-10">
                <h4 class="text-2xl font-bold text-white tracking-tight mb-2">Salud del Microservicio IA</h4>
                <p class="text-slate-500 text-sm mb-8">El motor ARS está procesando peticiones a través de Docker.</p>
                
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 bg-slate-900/50 rounded-2xl border border-slate-800">
                        <span class="text-sm font-medium text-slate-300">Respuesta API</span>
                        <span class="px-3 py-1 bg-green-500/10 text-green-500 text-[10px] font-bold rounded-lg border border-green-500/20">ÓPTIMO</span>
                    </div>
                </div>
            </div>
            
            <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-blue-500/10 rounded-full blur-3xl"></div>
        </div>

        <div class="glass p-10 rounded-[2.5rem] relative overflow-hidden">
            <h4 class="text-2xl font-bold text-white tracking-tight mb-2">Accesos Recientes</h4>
            <p class="text-slate-500 text-sm mb-6">Últimos registros de administración.</p>
            
            <div class="space-y-4">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-slate-800 flex items-center justify-center">
                        <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-white">Sesión iniciada</p>
                        <p class="text-xs text-slate-500">hace 5 minutos - IP: 172.18.0.3</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .animate-fade-in { animation: fadeIn 0.6s ease-out; }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
@endsection
