<?php

namespace App\Http\Controllers;

use App\Models\Tema;
use App\Models\Transcripcion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
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
            $urlIA = config('audio.ia.upload_url');
            if (!$urlIA) {
                 $estado['ai_service'] = 'not_configured';
            } else {
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
     * Sube un audio y lo envía a procesar a la IA (Arquitectura "Patata Caliente").
     * POST /api/temas/{id}/procesar-audio
     *
     * El archivo se hace streaming directamente a IA sin guardarse en el backend.
     */
    public function procesarAudio(Request $peticion, string $id_tema)
    {
        // 1. Validaciones
        $maxSize = config('audio.max_size_mb', 2048) * 1024; // MB a KB para Laravel
        $peticion->validate([
            'audio' => 'required|file|mimes:wav,mp3,m4a,flac,ogg|max:' . $maxSize,
            'idioma' => 'string|in:auto,es,en',
        ]);

        $tema = Tema::where('id_tema', $id_tema)
            ->whereHas('asignatura', function ($consulta) {
                $consulta->where('id_usuario', Auth::user()->id_usuario);
            })
            ->firstOrFail();
        $uuid = Str::uuid()->toString();

        // 2. Crear registro preliminar de Transcripción
        $transcripcion = Transcripcion::create([
            'id_tema' => $tema->id_tema,
            'uuid_referencia' => $uuid,
            'estado' => 'SUBIENDO',
            'nombre_archivo_original' => $peticion->file('audio')->getClientOriginalName(),
            'titulo' => 'Subiendo: ' . $peticion->file('audio')->getClientOriginalName(),
            'duracion_segundos' => 0,
            'progreso_porcentaje' => 0,
        ]);

        // 3. Streaming forward a IA (proxy streaming - el archivo nunca toca el disco del backend)
        try {
            $this->forwardToIA(
                $peticion->file('audio'),
                $uuid,
                $peticion->idioma ?? 'auto'
            );

            // 4. Actualizar estado a ENCOLADO
            $transcripcion->update([
                'estado' => 'ENCOLADO',
                'titulo' => 'En cola: ' . $transcripcion->nombre_archivo_original,
            ]);

            // 5. Retornar UUID para que frontend se suscriba a SSE
            return response()->json([
                'uuid' => $uuid,
                'estado' => 'ENCOLADO',
                'message' => 'Archivo subido. Procesando en cola...',
            ]);

        } catch (\Exception $e) {
            Log::error("Error al enviar audio a IA: " . $e->getMessage());

            $transcripcion->update([
                'estado' => 'FALLIDO',
                'error_mensaje' => 'Error al enviar a IA: ' . $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error al procesar',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Hace streaming forward del archivo de audio a IA.
     * El archivo nunca se guarda en el backend.
     */
    private function forwardToIA($file, string $uuid, string $idioma): void
    {
        $laravelUrl = rtrim(config('audio.ia.laravel_url', config('app.url')), '/');
        $callbackUrl = $laravelUrl . '/api/ia/callback?secret=' . config('audio.ia.callback_secret');

        $uploadUrl = config('audio.ia.upload_url');
        $timeout = config('audio.ia.timeout', 7200);

        // Using Laravel's Http facade for testability
        $response = Http::timeout($timeout)
            ->attach('audio', fopen($file->getRealPath(), 'r'), $uuid . '.' . $file->getClientOriginalExtension())
            ->asMultipart()
            ->post("$uploadUrl/upload", [
                [
                    'name' => 'uuid',
                    'contents' => $uuid,
                ],
                [
                    'name' => 'idioma',
                    'contents' => $idioma,
                ],
                [
                    'name' => 'callback_url',
                    'contents' => $callbackUrl,
                ],
            ]);

        if (!$response->successful()) {
            throw new \Exception('Error al comunicar con el servicio de IA: ' . $response->body());
        }
    }

    /**
     * Callback desde IA cuando completa el procesamiento.
     * POST /api/ia/callback
     */
    public function procesarCallback(Request $request)
    {
        // 1. Validar secret de autenticación
        $secret = $request->header('Authorization', '');
        $expectedSecret = 'Bearer ' . config('audio.ia.callback_secret');

        if ($secret !== $expectedSecret) {
            Log::warning("Intento de callback IA con secret inválido");
            return response()->json(['error' => 'No autorizado'], 401);
        }

        // 2. Validar datos
        $request->validate([
            'uuid' => 'required|string|exists:transcripciones,uuid_referencia',
            'estado' => 'required|in:COMPLETADO,FALLIDO',
            'resultado' => 'required_if:estado,COMPLETADO|array',
            'error' => 'required_if:estado,FALLIDO|string',
        ]);

        $uuid = $request->uuid;
        $transcripcion = Transcripcion::where('uuid_referencia', $uuid)->firstOrFail();

        // 3. Procesar según estado
        if ($request->estado === 'COMPLETADO') {
            $resultado = $request->resultado;

            $transcripcion->update([
                'estado' => 'COMPLETADO',
                'progreso_porcentaje' => 100,
                'etapa_actual' => null,
                'titulo' => 'Transcripción: ' . $transcripcion->nombre_archivo_original,
                'texto_plano' => collect($resultado['transcripcion'])->pluck('texto')->join("\n"),
                'texto_diarizado' => $resultado['transcripcion'],
                'duracion_segundos' => $resultado['metricas_rendimiento']['duracion_audio_segundos'] ?? 0,
                'fecha_procesamiento' => now(),
            ]);

            Log::info("Transcripción {$uuid} completada exitosamente");

        } else {
            $transcripcion->update([
                'estado' => 'FALLIDO',
                'error_mensaje' => $request->error,
            ]);

            Log::error("Transcripción {$uuid} fallida: " . $request->error);
        }

        return response()->json(['ok' => true]);
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
