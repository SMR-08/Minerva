@extends('layouts.admin')

@section('header', 'Consola de Debug IA')

@section('content')
<div class="space-y-6" x-data="debugConsole()">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- AI Connection Test -->
        <div class="glass rounded-[2rem] p-8 space-y-6">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-blue-500/10 flex items-center justify-center text-blue-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-white leading-tight">Estado del Servicio</h3>
                    <p class="text-sm text-slate-500">Verifica la conexión con el microservicio ARS.</p>
                </div>
            </div>

            <div class="p-6 bg-slate-900/50 rounded-2xl border border-slate-800 space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-slate-400">Endpoint:</span>
                    <code class="text-xs text-blue-400 bg-blue-400/5 px-2 py-1 rounded">{{ config('services.ai_service.url') }}</code>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-slate-400">Estado:</span>
                    <template x-if="pinging">
                        <span class="text-xs text-slate-500 animate-pulse">Consultando...</span>
                    </template>
                    <template x-if="!pinging && lastStatus">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full" :class="lastStatus === 'success' ? 'bg-green-500' : 'bg-red-500'"></span>
                            <span class="text-xs font-bold" :class="lastStatus === 'success' ? 'text-green-500' : 'text-red-500'" x-text="lastStatus === 'success' ? 'EN LÍNEA' : 'ERROR'"></span>
                        </div>
                    </template>
                </div>
            </div>

            <button @click="testConnection" :disabled="pinging" class="w-full py-4 bg-slate-800 hover:bg-slate-700 text-white rounded-2xl text-sm font-bold transition-all flex items-center justify-center gap-2 disabled:opacity-50">
                <svg x-show="!pinging" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <svg x-show="pinging" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                Probar Conexión
            </button>
        </div>

        <!-- Audio Upload Test -->
        <div class="glass rounded-[2rem] p-8 space-y-6">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-purple-500/10 flex items-center justify-center text-purple-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-white leading-tight">Procesar Audio</h3>
                    <p class="text-sm text-slate-500">Sube un archivo para probar la transcripción.</p>
                </div>
            </div>

            <form @submit.prevent="uploadAudio" class="space-y-4">
                <div class="relative group">
                    <input type="file" @change="handleFile" accept="audio/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                    <div class="p-8 border-2 border-dashed border-slate-800 group-hover:border-purple-500/50 rounded-2xl bg-slate-900/30 text-center transition-all">
                        <svg class="w-8 h-8 text-slate-600 group-hover:text-purple-500 mx-auto mb-3 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        <p class="text-sm text-slate-400" x-text="file ? file.name : 'Seleccionar o soltar archivo de audio'"></p>
                        <p class="text-xs text-slate-600 mt-1">MP3, WAV, M4A (Max 50MB)</p>
                    </div>
                </div>

                <div class="space-y-2" x-show="uploading">
                    <div class="flex justify-between text-[10px] font-bold text-slate-500 uppercase">
                        <span>Subiendo y Procesando...</span>
                        <span x-text="progress + '%'"></span>
                    </div>
                    <div class="h-1.5 w-full bg-slate-800 rounded-full overflow-hidden">
                        <div class="h-full bg-purple-500 transition-all duration-300" :style="'width: ' + progress + '%'"></div>
                    </div>
                </div>

                <button type="submit" :disabled="!file || uploading" class="w-full py-4 bg-purple-600 hover:bg-purple-500 text-white rounded-2xl text-sm font-bold shadow-lg shadow-purple-500/20 transition-all disabled:opacity-50 disabled:shadow-none">
                    Comenzar Transcripción
                </button>
            </form>
        </div>
    </div>

    <!-- Results Console -->
    <div class="glass rounded-[2rem] overflow-hidden">
        <div class="px-8 py-5 border-b border-slate-800 flex items-center justify-between">
            <h3 class="text-sm font-bold text-white uppercase tracking-widest">Salida de la Consola</h3>
            <button @click="clearConsole" class="text-[10px] font-bold text-slate-500 hover:text-white uppercase tracking-widest">Limpiar</button>
        </div>
        <div x-ref="consoleContainer" class="p-8 bg-black/40 font-mono text-sm min-h-[300px] max-h-[600px] overflow-y-auto custom-scrollbar">
            <template x-for="(log, index) in logs" :key="index">
                <div class="mb-4">
                    <div class="flex items-center gap-3 mb-1">
                        <span class="text-[10px] py-0.5 px-2 rounded font-bold uppercase tracking-tighter" :class="log.type === 'error' ? 'bg-red-500/20 text-red-500' : 'bg-blue-500/20 text-blue-500'" x-text="log.type"></span>
                        <span class="text-[10px] text-slate-600" x-text="log.time"></span>
                    </div>
                    <pre class="text-slate-300 whitespace-pre-wrap break-all" x-text="typeof log.content === 'object' ? JSON.stringify(log.content, null, 2) : log.content"></pre>
                </div>
            </template>
            <div x-show="logs.length === 0" class="flex flex-col items-center justify-center py-20 opacity-20">
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
                // Auto-scroll al final después de que Alpine renderice
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
                    // Simulación inicial de progreso
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
                        // Reset form
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
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #334155; }
</style>
@endsection
