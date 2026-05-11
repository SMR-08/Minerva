<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Models\Transcripcion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminDashboardController extends Controller
{
    /**
     * Dashboard Principal con estadísticas.
     */
    public function index()
    {
        // Estadísticas Reales
        $stats = [
            'total_users' => Usuario::count(),
            'total_transcriptions' => Transcripcion::count(),
            'storage_used' => $this->getStorageSize(),
            'ai_queue' => $this->getAIQueueStatus(),
        ];

        return view('admin.dashboard', compact('stats'));
    }

    /**
     * Consola de Debug para pruebas de IA y Backend.
     */
    public function debug()
    {
        return view('admin.debug');
    }

    /**
     * Prueba la conexión con el microservicio de IA.
     */
    public function testIA()
    {
        try {
            $urlIA = config('audio.ia.upload_url');
            $respuesta = Http::timeout(config('audio.ia.timeout', 120))
                             ->get("$urlIA/estado");

            if ($respuesta->successful()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Conexión exitosa con el microservicio de IA.',
                    'data' => $respuesta->json()
                ]);
            }

            Log::error("La IA respondió con error", ['status' => $respuesta->status()]);
            return response()->json([
                'status' => 'error_interno',
                'message' => 'Error interno al conectar con el servicio de IA'
            ], 502);

        } catch (\Exception $e) {
            Log::error("Fallo la conexión a la IA", [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno al conectar con el servicio de IA'
            ], 500);
        }
    }

    /**
     * Subir audio y enviarlo al microservicio de IA.
     */
    public function uploadAudio(Request $request)
    {
        $request->validate([
            'audio' => 'required|file|mimes:mp3,wav,m4a,flac,ogg|max:51200', // máximo 50MB
        ]);

        try {
            $audio = $request->file('audio');
            $inputPath = trim(config('audio.ia.input_path', '/app/compartido/entrada'), '"\'');
            $absolutePath = str_starts_with($inputPath, '/') ? $inputPath : base_path($inputPath);

            $fileName = time() . '_' . $audio->getClientOriginalName();
            $audio->move($absolutePath, $fileName);

            $urlIA = config('audio.ia.upload_url');
            $respuesta = Http::timeout(config('audio.ia.timeout', 120))
                ->post("$urlIA/transcribir_diarizado", [
                    'nombre_archivo' => $fileName,
                ]);

            if ($respuesta->successful()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Audio subido y enviado a procesar.',
                    'data' => $respuesta->json()
                ]);
            }

            Log::error("La IA respondió con error", [
                'status' => $respuesta->status(),
                'body' => $respuesta->body(),
            ]);
            return response()->json([
                'status' => 'error_interno',
                'message' => 'Error interno al conectar con el servicio de IA'
            ], 502);

        } catch (\Exception $e) {
            Log::error("Error al procesar subida de audio", [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtiene el estado completo de la cola de transcripciones.
     */
    public function queueStatus()
    {
        $colaIA = $this->consultarEstadoColaIA() ?? ['estado' => 'desconocido', 'peticiones_en_espera' => 0];

        $transcripcionesEncoladas = Transcripcion::whereIn('estado', ['ENCOLADO', 'PROCESANDO'])
            ->with('tema.asignatura')
            ->orderBy('fecha_grabacion', 'asc')
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id_transcripcion,
                    'uuid' => $t->uuid_referencia,
                    'titulo' => $t->titulo,
                    'estado' => $t->estado,
                    'progreso' => $t->progreso_porcentaje,
                    'etapa' => $t->etapa_actual,
                    'intentos' => $t->intentos,
                    'asignatura' => $t->tema?->asignatura?->nombre,
                    'tema' => $t->tema?->nombre,
                    'fecha' => $t->fecha_grabacion?->format('d/m/Y H:i:s'),
                ];
            });

        $ultimasCompletadas = Transcripcion::where('estado', 'COMPLETADO')
            ->orderBy('fecha_procesamiento', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id_transcripcion,
                    'titulo' => $t->titulo,
                    'duracion' => $t->duracion_segundos,
                    'fecha' => $t->fecha_procesamiento?->format('d/m/Y H:i:s'),
                ];
            });

        $ultimasFallidas = Transcripcion::where('estado', 'FALLIDO')
            ->orderBy('fecha_procesamiento', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id_transcripcion,
                    'titulo' => $t->titulo,
                    'error' => $t->error_mensaje,
                    'intentos' => $t->intentos,
                    'fecha' => $t->fecha_procesamiento?->format('d/m/Y H:i:s'),
                ];
            });

        $estadisticas = [
            'fuente' => 'Base de datos Laravel (jobs procesados por el worker)',
            'total_transcripciones' => Transcripcion::count(),
            'en_espera' => Transcripcion::where('estado', 'ENCOLADO')->count(),
            'procesando' => Transcripcion::where('estado', 'PROCESANDO')->count(),
            'completadas' => Transcripcion::where('estado', 'COMPLETADO')->count(),
            'fallidas' => Transcripcion::where('estado', 'FALLIDO')->count(),
        ];

        return response()->json([
            'status' => 'success',
            'cola_ia' => array_merge($colaIA, [
                'fuente' => 'API IA (' . config('audio.ia.upload_url') . '/estado_cola)',
            ]),
            'transcripciones_activas' => $transcripcionesEncoladas,
            'ultimas_completadas' => $ultimasCompletadas,
            'ultimas_fallidas' => $ultimasFallidas,
            'estadisticas' => $estadisticas,
        ]);
    }

    private function getStorageSize()
    {
        try {
            return Cache::remember('admin.storage_size', now()->addMinutes(5), function () {
                $inputPath = trim(config('audio.ia.input_path', '/app/compartido/entrada'), '"\'');
                $path = str_starts_with($inputPath, '/') ? $inputPath : base_path($inputPath);

                if (!file_exists($path)) return '0 MB';

                $size = 0;
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path)) as $file) {
                    $size += $file->getSize();
                }

                return round($size / 1024 / 1024, 2) . ' MB';
            });
        } catch (\Exception $e) {
            Log::warning('No se pudo cachear el tamano de almacenamiento', [
                'exception' => $e->getMessage(),
            ]);
            return 'N/A';
        }
    }

    private function consultarEstadoColaIA(): ?array
    {
        try {
            $urlIA = config('audio.ia.upload_url');
            $res = Http::timeout(3)->get("$urlIA/estado_cola");
            if ($res->successful()) {
                return $res->json();
            }
        } catch (\Exception $e) {
            Log::warning("No se pudo consultar el estado de la cola IA", [
                'exception' => $e->getMessage(),
            ]);
        }
        return null;
    }

    private function getAIQueueStatus()
    {
        $data = $this->consultarEstadoColaIA();
        return data_get($data, 'cola_espera', 'N/A');
    }
}
