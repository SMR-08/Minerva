@extends('layouts.admin')

@section('header', 'Gestión de Usuarios')

@section('content')
<div class="space-y-6" x-data="userManagement()">
    <!-- Flash Messages -->
    @if(session('success'))
    <div class="p-4 mb-4 text-sm text-green-400 glass rounded-2xl border border-green-500/20 flex items-center gap-3 animate-fade-in">
        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="p-4 mb-4 text-sm text-red-400 glass rounded-2xl border border-red-500/20 flex items-center gap-3 animate-fade-in">
        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('error') }}
    </div>
    @endif

    @if($errors->any())
    <div class="p-4 mb-4 text-sm text-red-400 glass rounded-2xl border border-red-500/20 flex flex-col gap-2 animate-fade-in">
        <div class="flex items-center gap-3 font-bold">
            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Por favor, corrige los siguientes errores:
        </div>
        <ul class="list-disc pl-11">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- Action Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="relative w-full md:w-96">
            <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </span>
            <input type="text" placeholder="Buscar por email o nombre..." class="block w-full pl-11 pr-4 py-3 glass rounded-2xl text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 transition-all">
        </div>
        <button @click="openModal('create')" class="px-6 py-3 bg-blue-600 hover:bg-blue-500 text-white rounded-2xl text-sm font-bold shadow-lg shadow-blue-500/20 transition-all flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nuevo Usuario
        </button>
    </div>

    <!-- Users Table -->
    <div class="glass rounded-[2rem] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-800">
                        <th class="px-8 py-5 text-xs font-bold text-slate-500 uppercase tracking-widest">Usuario</th>
                        <th class="px-8 py-5 text-xs font-bold text-slate-500 uppercase tracking-widest">Rol</th>
                        <th class="px-8 py-5 text-xs font-bold text-slate-500 uppercase tracking-widest">Estado</th>
                        <th class="px-8 py-5 text-xs font-bold text-slate-500 uppercase tracking-widest text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50">
                    @forelse($usuarios as $u)
                    <tr class="group hover:bg-slate-800/30 transition-colors">
                        <td class="px-8 py-5">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl bg-slate-800 flex items-center justify-center border border-slate-700 font-bold text-blue-400">
                                    {{ substr($u->nombre_completo, 0, 1) }}
                                </div>
                                <div class="overflow-hidden">
                                    <p class="text-sm font-semibold text-white tracking-tight">{{ $u->nombre_completo }}</p>
                                    <p class="text-xs text-slate-500 truncate">{{ $u->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-8 py-5">
                            <span class="px-3 py-1 text-[10px] font-bold rounded-lg uppercase tracking-widest {{ ($u->rol->nombre ?? '') === 'ADMIN' ? 'bg-amber-500/10 text-amber-500 border border-amber-500/20' : 'bg-blue-500/10 text-blue-500 border border-blue-500/20' }}">
                                {{ $u->rol->nombre ?? 'Usuario' }}
                            </span>
                        </td>
                        <td class="px-8 py-5">
                            @php
                                $estadoNombre = $u->estado->nombre ?? 'DESCONOCIDO';
                                $color = match($estadoNombre) {
                                    'ACTIVO' => 'green',
                                    'SUSPENDIDO' => 'amber',
                                    'BANEADO' => 'red',
                                    default => 'slate'
                                };
                            @endphp
                            <div class="flex items-center text-xs text-{{ $color }}-500 gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-{{ $color }}-500 {{ $estadoNombre === 'ACTIVO' ? 'animate-pulse' : '' }}"></span>
                                {{ ucfirst(strtolower($estadoNombre)) }}
                            </div>
                        </td>
                        <td class="px-8 py-5 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button @click="editUser({{ json_encode($u) }})" class="p-2 text-slate-500 hover:text-white hover:bg-slate-800 rounded-lg transition-all" title="Editar">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <form action="{{ route('admin.usuarios.destroy', $u->id_usuario) }}" method="POST" onsubmit="return confirm('¿Estás seguro de eliminar este usuario?')" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 text-slate-500 hover:text-red-500 hover:bg-red-500/10 rounded-lg transition-all" title="Eliminar">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-8 py-12 text-center">
                            <p class="text-slate-500 text-sm">No se encontraron usuarios registrados.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($usuarios->hasPages())
        <div class="px-8 py-6 border-t border-slate-800 bg-slate-900/50">
            {{ $usuarios->links() }}
        </div>
        @endif
    </div>

    <!-- User Modal (Create/Edit) -->
    <div x-show="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm" x-cloak>
        <div class="glass w-full max-w-lg rounded-[2.5rem] overflow-hidden animate-zoom-in" @click.away="modalOpen = false">
            <div class="px-10 py-8">
                <h3 class="text-xl font-bold text-white mb-6" x-text="mode === 'create' ? 'Nuevo Usuario' : 'Editar Usuario'"></h3>
                
                <form :action="formAction" method="POST" class="space-y-5">
                    @csrf
                    <template x-if="mode === 'edit'">
                        <input type="hidden" name="_method" value="PUT">
                    </template>

                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-widest">Nombre Completo</label>
                        <input type="text" name="nombre_completo" x-model="formData.nombre_completo" required class="block w-full px-5 py-3 glass rounded-2xl text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500/50 transition-all">
                    </div>

                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-widest">Email</label>
                        <input type="email" name="email" x-model="formData.email" required class="block w-full px-5 py-3 glass rounded-2xl text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500/50 transition-all">
                    </div>

                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-widest">Password <small class="text-slate-500" x-show="mode === 'edit'">(Opcional)</small></label>
                        <input type="password" name="password" :required="mode === 'create'" class="block w-full px-5 py-3 glass rounded-2xl text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500/50 transition-all">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-widest">Rol</label>
                            <select name="id_rol" x-model="formData.id_rol" class="block w-full px-5 py-3 glass rounded-2xl text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500/50 transition-all">
                                @foreach($roles as $rol)
                                <option value="{{ $rol->id_rol }}">{{ $rol->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-widest">Estado</label>
                            <select name="id_estado" x-model="formData.id_estado" class="block w-full px-5 py-3 glass rounded-2xl text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500/50 transition-all">
                                @foreach($estados as $est)
                                <option value="{{ $est->id_estado }}">{{ $est->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="pt-6 flex items-center justify-end gap-4">
                        <button type="button" @click="modalOpen = false" class="px-6 py-3 text-sm font-bold text-slate-400 hover:text-white transition-colors">Cancelar</button>
                        <button type="submit" class="px-8 py-3 bg-blue-600 hover:bg-blue-500 text-white rounded-2xl text-sm font-bold shadow-lg shadow-blue-500/20 transition-all" x-text="mode === 'create' ? 'Crear Usuario' : 'Guardar Cambios'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function userManagement() {
        return {
            modalOpen: false,
            mode: 'create',
            formAction: "{{ route('admin.usuarios.store') }}",
            formData: {
                id_usuario: '',
                nombre_completo: '',
                email: '',
                id_rol: '2',
                id_estado: '1'
            },
            openModal(mode) {
                this.mode = mode;
                this.modalOpen = true;
                if (mode === 'create') {
                    this.formAction = "{{ route('admin.usuarios.store') }}";
                    this.formData = { nombre_completo: '', email: '', id_rol: '2', id_estado: '1' };
                }
            },
            editUser(user) {
                this.mode = 'edit';
                this.formAction = `/admin/usuarios/${user.id_usuario}`;
                this.formData = {
                    id_usuario: user.id_usuario,
                    nombre_completo: user.nombre_completo,
                    email: user.email,
                    id_rol: user.id_rol,
                    id_estado: user.id_estado
                };
                this.modalOpen = true;
            }
        }
    }
</script>

<style>
    [x-cloak] { display: none !important; }
    .animate-zoom-in { animation: zoomIn 0.3s ease-out; }
    @keyframes zoomIn {
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
    }
    .animate-fade-in { animation: fadeIn 0.4s ease-out; }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
@endsection
