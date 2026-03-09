<?php

namespace App\Http\Controllers;

use App\Models\Tema;
use App\Models\Transcripcion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;

class ProcesamientoAudioController extends Controller
{
    /**
     * Verificar estado del servicio IA y base de datos.
     * GET /api/ia/estado
     */
    public function verificarEstado()
    {
        $estado = [
            'backend_laravel' => 'online',
            'database' => 'unknown',
            'ai_service' => 'unknown',
            'ai_queue_status' => null,
            'timestamp' => now()->toIso8601String(),
        ];

        // 1. Verificar BD
        try {
            DB::connection()->getPdo();
            $estado['database'] = 'connected';
        } catch (\Exception $e) {
            $estado['database'] = 'disconnected: ' . $e->getMessage();
        }

        // 2. Verificar Servicio IA
        try {
            $urlIA = env('AI_BACKEND_URL');
            if (!$urlIA) {
                 $estado['ai_service'] = 'not_configured';
            } else {
                // Asumimos que el backend de IA tiene un endpoint / o /health o /estado_cola
                $respuesta = Http::timeout(2)->get("$urlIA/estado_cola");
                
                if ($respuesta->successful()) {
                    $estado['ai_service'] = 'online';
                    $estado['ai_queue_status'] = $respuesta->json();
                } else {
                    $estado['ai_service'] = 'error: ' . $respuesta->status();
                }
            }
        } catch (\Exception $e) {
            $estado['ai_service'] = 'unreachable: ' . $e->getMessage();
        }

        return response()->json($estado);
    }

    /**
     * Sube un audio y lo envía a procesar a la IA.
     * POST /api/temas/{id}/procesar-audio
     */
    public function procesarAudio(Request $peticion, string $id_tema)
    {
        // 1. Validaciones
        $peticion->validate([
            'audio' => 'required|file|mimes:wav,mp3,m4a|max:512000', // Max 500MB
            'idioma' => 'string|in:auto,es,en',
        ]);

        $tema = Tema::findOrFail($id_tema);
        
        // TODO: Verificar propiedad del tema (Security 10/10)
        // $this->authorize('update', $tema);

        // 2. Guardar archivo en el FS Compartido
        $archivo = $peticion->file('audio');
        $uuid = Str::uuid();
        $nombreArchivo = $uuid . '.' . $archivo->getClientOriginalExtension();
        
        // Guardamos en el disco local configurado en FILESYSTEM_DISK -> root
        // Pero necesitamos que esté en AI_INPUT_PATH.
        $rutaDestino = env('AI_INPUT_PATH');
        
        if (!is_dir($rutaDestino)) {
             return response()->json(['error' => 'Configuración de servidor incorrecta (Ruta IA no existe)'], 500);
        }

        $archivo->move($rutaDestino, $nombreArchivo);

        // 3. Crear registro preliminar de Transcripción
        $transcripcion = Transcripcion::create([
            'id_tema' => $tema->id_tema,
            'uuid_referencia' => $uuid,
            'nombre_archivo_original' => $archivo->getClientOriginalName(),
            'titulo' => 'Procesando: ' . $archivo->getClientOriginalName(),
            'duracion_segundos' => 0, // Se actualizará al recibir respuesta
            // 'estado' => 'PROCESANDO' // Si tuviéramos una columna estado
        ]);

        // 4. Llamar a la IA (Sin Bloquear idealmente, pero MVP Bloqueante/Timeout largo)
        try {
            $urlIA = env('AI_BACKEND_URL');
            $timeout = env('AI_TIMEOUT', 300);

            // Verificar estado cola
            $respuestaEstado = Http::timeout(5)->get("$urlIA/estado_cola");
            
            if ($respuestaEstado->successful() && $respuestaEstado->json('estado') === 'ocupado') {
                return response()->json([
                   'message' => 'Archivo subido, pero la IA está ocupada. Inténtalo de nuevo más tarde.',
                   'transcripcion_id' => $transcripcion->id_transcripcion
                ], 202); // Accepted
            }

            // Enviar petición de procesado
            $respuesta = Http::timeout($timeout)->post("$urlIA/transcribir_diarizado", [
                'nombre_archivo' => $nombreArchivo,
                'idioma' => $peticion->idioma ?? 'auto',
                'activar_asignacion_roles' => true,
            ]);

            if ($respuesta->successful()) {
                $datos = $respuesta->json();
                
                // 5. Actualizar Transcripción con resultados
                $transcripcion->update([
                    'titulo' => 'Transcripción: ' . $archivo->getClientOriginalName(),
                    'texto_plano' => collect($datos['transcripcion'])->pluck('texto')->join("\n"),
                    'texto_diarizado' => $datos['transcripcion'], // Casted a array JSON
                    'duracion_segundos' => $datos['metricas_rendimiento']['duracion_audio_segundos'] ?? 0,
                    'fecha_procesamiento' => now(),
                ]);

                return response()->json($transcripcion);
            } else {
                Log::error("Error IA: " . $respuesta->body());
                return response()->json(['error' => 'Error al procesar con IA', 'details' => $respuesta->json()], 502);
            }

        } catch (\Exception $e) {
            Log::error("Excepción IA: " . $e->getMessage());
            return response()->json(['error' => 'Error de conexión con servicio IA', 'msg' => $e->getMessage()], 503);
        }
    }

    /**
     * Listar transcripciones del usuario autenticado.
     * GET /api/transcripciones
     */
    public function index(Request $request)
    {
        $usuario = $request->user();
        
        // Obtenemos las transcripciones a través de la relación:
        // Usuario -> Asignaturas -> Temas -> Transcripciones
        $transcripciones = Transcripcion::whereHas('tema.asignatura', function($query) use ($usuario) {
            $query->where('id_usuario', $usuario->id_usuario);
        })
        ->with(['tema.asignatura'])
        ->orderBy('fecha_procesamiento', 'desc')
        ->get();

        return response()->json($transcripciones);
    }

    /**
     * Obtener transcripción final
     * GET /api/transcripciones/{id}
     */
    public function show(string $id) 
    {
        return response()->json(Transcripcion::with('tema.asignatura')->findOrFail($id));
    }
}
