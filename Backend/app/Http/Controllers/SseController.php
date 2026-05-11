<?php

namespace App\Http\Controllers;

use App\Models\Transcripcion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class SseController extends Controller
{
    /**
     * Estado de procesamiento de una transcripción (polling, no SSE).
     * GET /api/transcripciones/{uuid}/estado?token=xxx
     *
     * Devuelve JSON inmediatamente. El frontend hace polling cada 2s.
     * Esto evita bloquear workers PHP-FPM con while(true) como hacia
     * el SSE anterior. Con 20 usuarios concurrentes, cada peticion
     * libera el worker en <100ms en vez de ocuparlo 2 horas.
     */
    public function estado(Request $request, string $uuid)
    {
        $transcripcion = Transcripcion::with('tema.asignatura')
            ->where('uuid_referencia', $uuid)->first();

        if (!$transcripcion) {
            return response()->json(['error' => 'No encontrado'], 404);
        }

        $userId = $transcripcion->tema->asignatura->id_usuario;

        $sseToken = $request->query('sse_token');
        $sanctumToken = $request->query('token');

        $autenticado = false;

        if ($sseToken) {
            $cachedUserId = Cache::get("sse_token:{$sseToken}");
            if ($cachedUserId && (int)$cachedUserId === $userId) {
                Cache::forget("sse_token:{$sseToken}");
                $autenticado = true;
            }
        }

        if (!$autenticado && $sanctumToken) {
            $accessToken = PersonalAccessToken::findToken($sanctumToken);
            if ($accessToken && (int)$accessToken->tokenable_id === $userId) {
                $autenticado = true;
            }
        }

        if (!$autenticado) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        // Construir payload (mismo formato que el SSE anterior)
        $payload = [
            'estado' => $transcripcion->estado,
            'titulo' => $transcripcion->titulo,
            'uuid' => $transcripcion->uuid_referencia,
        ];

        switch ($transcripcion->estado) {
            case 'SUBIENDO':
                $payload['mensaje'] = 'Subiendo archivo...';
                break;

            case 'ENCOLADO':
                $payload['posicion'] = $this->obtenerPosicionCola($uuid);
                $payload['mensaje'] = "En cola, posicion #{$payload['posicion']}";
                break;

            case 'PROCESANDO':
                $payload['progreso'] = $transcripcion->progreso_porcentaje ?? 0;
                $payload['etapa'] = $transcripcion->etapa_actual ?? 'ASR';
                $payload['eta_segundos'] = $this->calcularETA($uuid);
                $payload['mensaje'] = $this->obtenerMensajeEtapa($payload['etapa']);
                break;

            case 'COMPLETADO':
                $payload['url'] = url('/transcripcion/' . $transcripcion->id_transcripcion);
                $payload['mensaje'] = 'Completado!';
                break;

            case 'FALLIDO':
                $payload['error'] = $transcripcion->error_mensaje ?? 'Error desconocido';
                $payload['mensaje'] = 'Error al procesar';
                break;
        }

        return response()->json($payload);
    }

    /**
     * Obtiene la posición en la cola de procesamiento.
     */
    private function obtenerPosicionCola(string $uuid): int
    {
        $transcripcion = Transcripcion::where('uuid_referencia', $uuid)->first();

        if (!$transcripcion) {
            return 0;
        }

        // Contar transcripciones encoladas creadas antes que esta
        $posicion = Transcripcion::where('estado', 'ENCOLADO')
            ->where('fecha_grabacion', '<', $transcripcion->fecha_grabacion)
            ->count() + 1;

        return max(1, $posicion);
    }

    /**
     * Calcula el tiempo estimado restante (ETA) en segundos.
     */
    private function calcularETA(string $uuid): int
    {
        // Obtener estadísticas de procesamiento recientes
        $promedioDuracion = Transcripcion::where('estado', 'COMPLETADO')
            ->where('fecha_grabacion', '>', now()->subHours(2))
            ->avg('duracion_segundos');

        $promedioProcesamiento = Transcripcion::where('estado', 'COMPLETADO')
            ->where('fecha_grabacion', '>', now()->subHours(2))
            ->avg(DB::raw('TIMESTAMPDIFF(SECOND, fecha_grabacion, fecha_procesamiento)'));

        // Si no hay datos, estimar 5 minutos
        if (!$promedioDuracion || !$promedioProcesamiento) {
            return 300;
        }

        // Calcular factor de procesamiento
        $factor = $promedioProcesamiento / max(1, $promedioDuracion);

        // Obtener duración del audio actual
        $transcripcion = Transcripcion::where('uuid_referencia', $uuid)->first();
        $duracionRestante = $transcripcion->duracion_segundos ?? 0;

        return (int) ($duracionRestante * $factor);
    }

    /**
     * Obtiene el mensaje descriptivo de la etapa actual.
     */
    private function obtenerMensajeEtapa(string $etapa): string
    {
        $mensajes = [
            'ASR' => 'Transcribiendo audio...',
            'DIARIZACION' => 'Identificando hablantes...',
            'ALINEACION' => 'Alineando timestamps...',
            'POST_PROCESADO' => 'Finalizando...',
        ];

        return $mensajes[$etapa] ?? 'Procesando...';
    }

    /**
     * Genera un token temporal de un solo uso para SSE.
     * POST /api/sse/token
     */
    public function generarTokenSSE(Request $request)
    {
        $token = Str::uuid()->toString();
        Cache::put("sse_token:{$token}", $request->user()->id_usuario, now()->addSeconds(30));

        return response()->json(['token' => $token]);
    }

    /**
     * Endpoint para actualizaciones push desde IA (interno).
     * POST /api/ia/sse-update
     */
    public function sseUpdate(Request $request)
    {
        // Validar secret (mismo header que procesarCallback para coherencia)
        $secret = $request->header('X-Callback-Secret', '');
        $expectedSecret = config('audio.ia.callback_secret');

        if (!hash_equals($secret, $expectedSecret)) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $request->validate([
            'uuid' => 'required|string|exists:transcripciones,uuid_referencia',
            'estado' => 'required|string',
            'progreso' => 'integer|min:0|max:100',
            'etapa' => 'string',
        ]);

        $uuid = $request->uuid;
        $transcripcion = Transcripcion::where('uuid_referencia', $uuid)->firstOrFail();

        // Actualizar estado
        $updateData = [
            'estado' => $request->estado,
        ];

        if ($request->has('progreso')) {
            $updateData['progreso_porcentaje'] = $request->progreso;
        }

        if ($request->has('etapa')) {
            $updateData['etapa_actual'] = $request->etapa;
        }

        $transcripcion->update($updateData);

        return response()->json(['ok' => true]);
    }
}
