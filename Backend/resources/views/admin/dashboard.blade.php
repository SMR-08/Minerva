@extends('layouts.admin')

@section('header', 'Panel de Control')

@section('content')
<div class="space-y-6">
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Usuarios -->
        <div class="card">
            <div class="card-body">
                <div class="flex items-start justify-between mb-4">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background: #FFF4E6;">
                        <svg class="w-5 h-5" style="color: #F59E0B;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    </div>
                </div>
                <p class="text-sm font-medium mb-1" style="color: #666666;">Usuarios Totales</p>
                <h3 class="text-3xl font-semibold" style="color: #1a1a1a;">{{ number_format($stats['total_users']) }}</h3>
            </div>
        </div>

        <!-- Transcripciones -->
        <div class="card">
            <div class="card-body">
                <div class="flex items-start justify-between mb-4">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background: #EFF6FF;">
                        <svg class="w-5 h-5" style="color: #3B82F6;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                    </div>
                </div>
                <p class="text-sm font-medium mb-1" style="color: #666666;">Transcripciones</p>
                <h3 class="text-3xl font-semibold" style="color: #1a1a1a;">{{ number_format($stats['total_transcriptions']) }}</h3>
            </div>
        </div>

        <!-- Almacenamiento -->
        <div class="card">
            <div class="card-body">
                <div class="flex items-start justify-between mb-4">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background: #D1FAE5;">
                        <svg class="w-5 h-5" style="color: #10B981;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                    </div>
                </div>
                <p class="text-sm font-medium mb-1" style="color: #666666;">Uso de Disco</p>
                <h3 class="text-3xl font-semibold" style="color: #1a1a1a;">{{ $stats['storage_used'] }}</h3>
            </div>
        </div>

        <!-- Cola de la IA -->
        <div class="card">
            <div class="card-body">
                <div class="flex items-start justify-between mb-4">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background: #FFF4E6;">
                        <svg class="w-5 h-5" style="color: #FFD866;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <p class="text-sm font-medium mb-1" style="color: #666666;">Cola de la IA</p>
                <h3 class="text-3xl font-semibold" style="color: #1a1a1a;">{{ $stats['ai_queue'] }}</h3>
            </div>
        </div>
    </div>

    <!-- System Status -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Estado del Sistema -->
        <div class="card">
            <div class="card-header">Estado del Sistema</div>
            <div class="card-body space-y-3">
                <div class="flex items-center justify-between p-3 rounded-lg" style="background: #F9FCFF; border: 1px solid #E5E7EB;">
                    <div class="flex items-center gap-3">
                        <div class="w-2 h-2 rounded-full" style="background: #10B981;"></div>
                        <span class="text-sm font-medium" style="color: #1a1a1a;">API Responde</span>
                    </div>
                    <span class="badge badge-success">Óptimo</span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-lg" style="background: #F9FCFF; border: 1px solid #E5E7EB;">
                    <div class="flex items-center gap-3">
                        <div class="w-2 h-2 rounded-full" style="background: #10B981;"></div>
                        <span class="text-sm font-medium" style="color: #1a1a1a;">Microservicio IA</span>
                    </div>
                    <span class="badge badge-success">Disponible</span>
                </div>
            </div>
        </div>

        <!-- Actividad Reciente -->
        <div class="card">
            <div class="card-header">Actividad Reciente</div>
            <div class="card-body space-y-3">
                <div class="flex items-center gap-3 p-3 rounded-lg" style="background: #F9FCFF;">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: #FFFFFF; border: 1px solid #E5E7EB;">
                        <svg class="w-4 h-4" style="color: #666666;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium" style="color: #1a1a1a;">Sesión iniciada</p>
                        <p class="text-xs" style="color: #999999;">hace 5 minutos - IP: 172.18.0.3</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
