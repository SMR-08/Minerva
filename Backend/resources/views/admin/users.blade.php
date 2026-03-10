@extends('layouts.admin')

@section('header', 'Gestión de Usuarios')

@section('content')
<div class="space-y-6" x-data="userManagement()">
    <!-- Flash Messages -->
    @if(session('success'))
    <div class="p-4 rounded-lg flex items-center gap-3" style="background: #D1FAE5; border: 1px solid #10B981; color: #065F46;">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="text-sm font-medium">{{ session('success') }}</span>
    </div>
    @endif

    @if(session('error'))
    <div class="p-4 rounded-lg flex items-center gap-3" style="background: #FEE2E2; border: 1px solid #EF4444; color: #991B1B;">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="text-sm font-medium">{{ session('error') }}</span>
    </div>
    @endif

    @if($errors->any())
    <div class="p-4 rounded-lg" style="background: #FEE2E2; border: 1px solid #EF4444; color: #991B1B;">
        <div class="flex items-center gap-3 font-semibold mb-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="text-sm">Por favor, corrige los siguientes errores:</span>
        </div>
        <ul class="list-disc pl-11 text-sm space-y-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- Action Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="relative w-full md:w-96">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center" style="color: #999999;">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </span>
            <input type="text" placeholder="Buscar por email o nombre..." class="input w-full pl-10">
        </div>
        <button @click="openModal('create')" class="btn-primary flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nuevo Usuario
        </button>
    </div>

    <!-- Users Table -->
    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($usuarios as $u)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center font-semibold" style="background: #F3FAFF; border: 1px solid #E5E7EB; color: #666666;">
                                {{ substr($u->nombre_completo, 0, 1) }}
                            </div>
                            <div>
                                <p class="text-sm font-medium" style="color: #1a1a1a;">{{ $u->nombre_completo }}</p>
                                <p class="text-xs" style="color: #999999;">{{ $u->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge {{ ($u->rol->nombre ?? '') === 'ADMIN' ? 'badge-warning' : 'badge-info' }}">
                            {{ $u->rol->nombre ?? 'Usuario' }}
                        </span>
                    </td>
                    <td>
                        @php
                            $estadoNombre = $u->estado->nombre ?? 'DESCONOCIDO';
                            $badgeClass = match($estadoNombre) {
                                'ACTIVO' => 'badge-success',
                                'SUSPENDIDO' => 'badge-warning',
                                'BANEADO' => 'badge-error',
                                default => 'badge-info'
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ ucfirst(strtolower($estadoNombre)) }}</span>
                    </td>
                    <td class="text-right">
                        <div class="flex items-center justify-end gap-2">
                            <button @click="editUser({{ json_encode($u) }})" class="btn-ghost p-2" title="Editar">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <form action="{{ route('admin.usuarios.destroy', $u->id_usuario) }}" method="POST" onsubmit="return confirm('¿Estás seguro de eliminar este usuario?')" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-ghost p-2" style="color: #EF4444;" title="Eliminar">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center py-12">
                        <p class="text-sm" style="color: #999999;">No se encontraron usuarios registrados.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        
        @if($usuarios->hasPages())
        <div class="px-6 py-4 border-t" style="border-color: #E5E7EB; background: #F9FCFF;">
            {{ $usuarios->links() }}
        </div>
        @endif
    </div>

    <!-- User Modal (Create/Edit) -->
    <div x-show="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0, 0, 0, 0.5);" x-cloak>
        <div class="w-full max-w-lg rounded-xl overflow-hidden" style="background: #FFFFFF; border: 1px solid #E5E7EB;" @click.away="modalOpen = false">
            <div class="px-8 py-6 border-b" style="border-color: #E5E7EB;">
                <h3 class="text-lg font-semibold" style="color: #1a1a1a;" x-text="mode === 'create' ? 'Nuevo Usuario' : 'Editar Usuario'"></h3>
            </div>
            
            <form :action="formAction" method="POST" class="px-8 py-6 space-y-5">
                @csrf
                <template x-if="mode === 'edit'">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div>
                    <label class="block text-sm font-medium mb-2" style="color: #1a1a1a;">Nombre Completo</label>
                    <input type="text" name="nombre_completo" x-model="formData.nombre_completo" required class="input w-full">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2" style="color: #1a1a1a;">Email</label>
                    <input type="email" name="email" x-model="formData.email" required class="input w-full">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2" style="color: #1a1a1a;">
                        Password 
                        <span class="text-xs font-normal" style="color: #999999;" x-show="mode === 'edit'">(Opcional)</span>
                    </label>
                    <input type="password" name="password" :required="mode === 'create'" class="input w-full">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: #1a1a1a;">Rol</label>
                        <select name="id_rol" x-model="formData.id_rol" class="input w-full">
                            @foreach($roles as $rol)
                            <option value="{{ $rol->id_rol }}">{{ $rol->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: #1a1a1a;">Estado</label>
                        <select name="id_estado" x-model="formData.id_estado" class="input w-full">
                            @foreach($estados as $est)
                            <option value="{{ $est->id_estado }}">{{ $est->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="pt-4 flex items-center justify-end gap-3">
                    <button type="button" @click="modalOpen = false" class="btn-secondary">Cancelar</button>
                    <button type="submit" class="btn-primary" x-text="mode === 'create' ? 'Crear Usuario' : 'Guardar Cambios'"></button>
                </div>
            </form>
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
</style>
@endsection
