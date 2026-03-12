@extends('layouts.admin')

@section('header', 'Debug Console')

@section('content')
<div class="space-y-6" x-data="debugConsole()">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- AI Connection Test -->
        <div class="card">
            <div class="card-header">Estado del Servicio</div>
            <div class="card-body space-y-4">
                <p class="text-sm" style="color: #666666;">Verifica la conexión con el microservicio ARS.</p>
                
                <div class="p-4 rounded-lg space-y-3" style="background: #F9FCFF; border: 1px solid #E5E7EB;">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium" style="color: #666666;">Endpoint:</span>
                        <code class="text-xs px-2 py-1 rounded" style="background: #F3FAFF; color: #3B82F6; border: 1px solid #E5E7EB;">{{ config('services.ai_service.url') }}</code>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium" style="color: #666666;">Estado:</span>
                        <template x-if="pinging">
                            <span class="text-xs" style="color: #999999;">Consultando...</span>
                        </template>
                        <template x-if="!pinging && lastStatus">
                            <span class="badge" :class="lastStatus === 'success' ? 'badge-success' : 'badge-error'" x-text="lastStatus === 'success' ? 'En línea' : 'Error'"></span>
                        </template>
                    </div>
                </div>

                <button @click="testConnection" :disabled="pinging" class="btn-primary w-full flex items-center justify-center gap-2" :style="pinging ? 'opacity: 0.5; cursor: not-allowed;' : ''">
                    <svg x-show="!pinging" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <svg x-show="pinging" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Probar Conexión
                </button>
            </div>
        </div>

        <!-- Audio Upload Test -->
        <div class="card">
            <div class="card-header">Procesar Audio</div>
            <div class="card-body space-y-4">
                <p class="text-sm" style="color: #666666;">Sube un archivo para probar la transcripción.</p>
                
                <form @submit.prevent="uploadAudio" class="space-y-4">
                    <div class="relative">
                        <input type="file" @change="handleFile" accept="audio/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                        <div class="p-8 rounded-lg text-center transition-all" style="border: 2px dashed #E5E7EB; background: #F9FCFF;">
                            <svg class="w-8 h-8 mx-auto mb-3" style="color: #999999;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            <p class="text-sm" style="color: #666666;" x-text="file ? file.name : 'Seleccionar o soltar archivo de audio'"></p>
                            <p class="text-xs mt-1" style="color: #999999;">MP3, WAV, M4A (Max 50MB)</p>
                        </div>
                    </div>

                    <div class="space-y-2" x-show="uploading">
                        <div class="flex justify-between text-xs font-medium" style="color: #666666;">
                            <span>Subiendo y Procesando...</span>
                            <span x-text="progress + '%'"></span>
                        </div>
                        <div class="h-2 w-full rounded-full overflow-hidden" style="background: #E5E7EB;">
                            <div class="h-full transition-all duration-300" style="background: #FFD866;" :style="'width: ' + progress + '%'"></div>
                        </div>
                    </div>

                    <button type="submit" :disabled="!file || uploading" class="btn-primary w-full" :style="(!file || uploading) ? 'opacity: 0.5; cursor: not-allowed;' : ''">
                        Comenzar Transcripción
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Results Console -->
    <div class="card">
        <div class="card-header flex items-center justify-between">
            <span>Salida de la Consola</span>
            <button @click="clearConsole" class="text-xs font-medium" style="color: #999999;">Limpiar</button>
        </div>
        <div x-ref="consoleContainer" class="p-6 font-mono text-sm min-h-[300px] max-h-[600px] overflow-y-auto" style="background: #1a1a1a; color: #F3FAFF;">
            <template x-for="(log, index) in logs" :key="index">
                <div class="mb-4">
                    <div class="flex items-center gap-3 mb-1">
                        <span class="text-xs py-0.5 px-2 rounded font-semibold" :class="log.type === 'error' ? 'badge-error' : (log.type === 'success' ? 'badge-success' : 'badge-info')" x-text="log.type"></span>
                        <span class="text-xs" style="color: #999999;" x-text="log.time"></span>
                    </div>
                    <pre class="whitespace-pre-wrap break-all" style="color: #F3FAFF;" x-text="typeof log.content === 'object' ? JSON.stringify(log.content, null, 2) : log.content"></pre>
                </div>
            </template>
            <div x-show="logs.length === 0" class="flex flex-col items-center justify-center py-20" style="opacity: 0.3;">
                <svg class="w-12 h-12 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <p class="text-xs">Esperando acciones...</p>
            </div>
        </div>
    </div>
</div>

<script>
    function debugConsole() {
        return {
            pinging: false,
            uploading: false,
            progress: 0,
            lastStatus: null,
            file: null,
            logs: [],
            
            addLog(type, content) {
                this.logs.push({
                    type,
                    content,
                    time: new Date().toLocaleTimeString()
                });
                this.$nextTick(() => {
                    const container = this.$refs.consoleContainer;
                    if(container) container.scrollTop = container.scrollHeight;
                });
            },

            clearConsole() {
                this.logs = [];
            },

            handleFile(e) {
                this.file = e.target.files[0];
            },

            async testConnection() {
                this.pinging = true;
                this.addLog('info', 'Iniciando test de conexión...');
                
                try {
                    const res = await fetch("{{ route('admin.debug.test-ia') }}", {
                        method: 'POST',
                        headers: { 
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    });
                    const data = await res.json();
                    this.lastStatus = data.status;
                    this.addLog(data.status === 'success' ? 'success' : 'error', data);
                } catch (e) {
                    this.lastStatus = 'error';
                    this.addLog('error', 'Error de red: ' + e.message);
                } finally {
                    this.pinging = false;
                }
            },

            async uploadAudio() {
                if (!this.file) return;
                
                this.uploading = true;
                this.progress = 10;
                this.addLog('info', `Subiendo archivo: ${this.file.name} (${(this.file.size / 1024 / 1024).toFixed(2)} MB)`);
                
                const formData = new FormData();
                formData.append('audio', this.file);
                
                try {
                    let intv = setInterval(() => {
                        if(this.progress < 90) this.progress += 5;
                        else clearInterval(intv);
                    }, 500);

                    const res = await fetch("{{ route('admin.debug.upload-audio') }}", {
                        method: 'POST',
                        headers: { 
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: formData
                    });
                    
                    clearInterval(intv);
                    this.progress = 100;
                    
                    const data = await res.json();
                    this.addLog(data.status === 'success' ? 'success' : 'error', data);
                    
                    if (data.status === 'success') {
                        this.file = null;
                        document.querySelector('input[type="file"]').value = '';
                    }
                } catch (e) {
                    this.addLog('error', 'Error al procesar subida: ' + e.message);
                } finally {
                    setTimeout(() => {
                        this.uploading = false;
                        this.progress = 0;
                    }, 1000);
                }
            }
        }
    }
</script>

<style>
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    .animate-spin {
        animation: spin 1s linear infinite;
    }
</style>
@endsection
