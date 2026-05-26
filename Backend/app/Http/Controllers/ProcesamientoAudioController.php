<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProcesarAudioRequest;
use App\Http\Requests\UpdateTranscripcionRequest;
use App\Http\Resources\TranscripcionResource;
use App\Services\AudioProcessingService;
use App\Services\TranscripcionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ProcesamientoAudioController extends Controller
{
    public function __construct(
        private AudioProcessingService $audioProcessingService,
        private TranscripcionService $transcripcionService,
    ) {}

    public function verificarEstado()
    {
        $estado = [
            'backend_laravel' => 'online',
            'database' => 'unknown',
            'ai_service' => 'unknown',
            'ai_queue_status' => null,
            'timestamp' => now()->toIso8601String(),
        ];

        try {
            DB::connection()->getPdo();
            $estado['database'] = 'connected';
        } catch (\Exception $e) {
            Log::error("Error al verificar base de datos", [
                'exception' => $e->getMessage(),
            ]);
            $estado['database'] = 'disconnected';
        }

        try {
            $urlIA = config('audio.ia.upload_url');
            if (! $urlIA) {
                $estado['ai_service'] = 'not_configured';
            } else {
                $respuesta = Http::timeout(2)->get("$urlIA/estado_cola");

                if ($respuesta->successful()) {
                    $estado['ai_service'] = 'online';
                    $estado['ai_queue_status'] = $respuesta->json();
                } else {
                    Log::error("Servicio IA respondió con error", ['status' => $respuesta->status()]);
                    $estado['ai_service'] = 'error_interno';
                }
            }
        } catch (\Exception $e) {
            // HTTP falló — fallback a Redis (modo distribuido)
            try {
                $pendientes = \Illuminate\Support\Facades\Redis::connection('ia')->llen('minerva_tasks');
                $estado['ai_service'] = 'online (distribuido)';
                $estado['ai_queue_status'] = ['peticiones_en_espera' => $pendientes];
            } catch (\Exception $redisEx) {
                $estado['ai_service'] = 'unreachable';
            }
        }

        return response()->json($estado);
    }

    public function procesarAudio(ProcesarAudioRequest $peticion, string $id_tema)
    {
        $transcripcion = $this->audioProcessingService->procesarAudio(
            $peticion->file('audio'),
            $id_tema,
            $peticion->user(),
            $peticion->validated(),
        );

        return response()->json([
            'uuid' => $transcripcion->uuid_referencia,
            'estado' => 'ENCOLADO',
            'message' => 'Archivo subido. Procesando en cola...',
        ], Response::HTTP_ACCEPTED);
    }

    public function procesarCallback(Request $request)
    {
        $secret = $request->header('X-Callback-Secret', '');
        $expectedSecret = config('audio.ia.callback_secret');

        if (! hash_equals($secret, $expectedSecret)) {
            Log::warning("Intento de callback IA con secret inválido");
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $request->validate([
            'uuid' => 'required|string|exists:transcripciones,uuid_referencia',
            'estado' => 'required|in:PROCESANDO,COMPLETADO,FALLIDO,RESUMIENDO,LISTO',
            'resultado' => 'required_if:estado,COMPLETADO|array',
            'error' => 'required_if:estado,FALLIDO|string',
            'resumen' => 'nullable|string',
        ]);

        $this->audioProcessingService->procesarCallback(
            $request->uuid,
            $request->only(['estado', 'resultado', 'error', 'resumen']),
        );

        return response()->json(['ok' => true]);
    }

    public function index(Request $request)
    {
        return TranscripcionResource::collection(
            $this->transcripcionService->listarPorUsuario($request->user())
        );
    }

    public function show(Request $request, string $id)
    {
        $transcripcion = $this->transcripcionService->obtenerPorIdYUsuario(
            (int) $id,
            $request->user()
        );

        return new TranscripcionResource($transcripcion);
    }

    public function update(UpdateTranscripcionRequest $peticion, string $id)
    {
        $transcripcion = $this->transcripcionService->obtenerPorIdYUsuario(
            (int) $id,
            $peticion->user()
        );

        $transcripcion = $this->transcripcionService->actualizar(
            $transcripcion,
            $peticion->validated(),
        );

        return new TranscripcionResource($transcripcion);
    }

    public function destroy(Request $request, string $id)
    {
        $transcripcion = $this->transcripcionService->obtenerPorIdYUsuario(
            (int) $id,
            $request->user()
        );

        $this->transcripcionService->eliminar($transcripcion);

        return response()->json(['message' => 'Transcripción eliminada correctamente']);
    }
}
