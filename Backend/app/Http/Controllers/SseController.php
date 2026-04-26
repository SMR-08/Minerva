<?php

namespace App\Http\Controllers;

use App\Models\Transcripcion;
use App\Models\AudioProcessingJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

class SseController extends Controller
{
    /**
     * Server-Sent Events para actualizaciones en tiempo real del procesamiento.
     * GET /api/transcripciones/{uuid}/estado
     */
    public function estado(Request $request, string $uuid)
    {
        // Headers CORS y SSE deben ir antes de cualquier salida
        header('Access-Control-Allow-Origin: ' . ($request->header('Origin') ?? '*'));
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');
        ini_set('output_buffering', 'off');
        ini_set('zlib.output_compression', false);
        ob_implicit_flush(true);

        if ($request->isMethod('OPTIONS')) { return; }

        // Autenticar via query param (EventSource no soporta headers)
        $token = $request->query('token');
        if (!$token || !PersonalAccessToken::findToken($token)) {
            echo "data: " . json_encode(['error' => 'No autorizado']) . "\n\n";
            if (ob_get_level()) ob_flush();
            flush();
            return;
        }

        // Buscar transcripción
        $transcripcion = Transcripcion::where('uuid_referencia', $uuid)->first();

        if (!$transcripcion) {
            echo "data: " . json_encode(['error' => 'No encontrado']) . "\n\n";
            if (ob_get_level()) ob_flush();
            flush();
            return;
        }

        $startTime = time();
        $timeout = config('audio.sse.timeout_seconds', 7200); // 2 horas
        $heartbeatInterval = config('audio.sse.heartbeat_seconds', 30);
        $lastHeartbeat = time();

        // Loop SSE: mantener conexión abierta
        while (true) {
            // Recargar datos desde BD
            $transcripcion->refresh();

            // Construir payload
            $payload = [
                'estado' => $transcripcion->estado,
                'titulo' => $transcripcion->titulo,
                'uuid' => $transcripcion->uuid_referencia,
            ];

            // Agregar datos específicos según estado
            switch ($transcripcion->estado) {
                case 'SUBIENDO':
                    $payload['mensaje'] = 'Subiendo archivo...';
                    break;

                case 'ENCOLADO':
                    $payload['posicion'] = $this->obtenerPosicionCola($uuid);
                    $payload['mensaje'] = "En cola, posición #{$payload['posicion']}";
                    break;

                case 'PROCESANDO':
                    $payload['progreso'] = $transcripcion->progreso_porcentaje ?? 0;
                    $payload['etapa'] = $transcripcion->etapa_actual ?? 'ASR';
                    $payload['eta_segundos'] = $this->calcularETA($uuid);
                    $payload['mensaje'] = $this->obtenerMensajeEtapa($payload['etapa']);
                    break;

                case 'COMPLETADO':
                    $payload['url'] = url('/transcripcion/' . $transcripcion->id_transcripcion);
                    $payload['mensaje'] = '¡Completado!';
                    break;

                case 'FALLIDO':
                    $payload['error'] = $transcripcion->error_mensaje ?? 'Error desconocido';
                    $payload['mensaje'] = 'Error al procesar';
                    break;
            }

            // Enviar evento SSE
            echo "data: " . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
            if (ob_get_level()) ob_flush();
            flush();

            // Salir si está completado o fallido
            if (in_array($transcripcion->estado, ['COMPLETADO', 'FALLIDO'])) {
                break;
            }

            // Heartbeat: enviar comentario vacío para mantener conexión viva
            if (time() - $lastHeartbeat >= $heartbeatInterval) {
                echo ": heartbeat\n\n";
                if (ob_get_level()) ob_flush();
                flush();
                $lastHeartbeat = time();
            }

            // Esperar 2 segundos antes de siguiente actualización
            usleep(2000000);

            // Timeout después del máximo configurado
            if (time() - $startTime > $timeout) {
                echo "data: " . json_encode(['error' => 'Timeout de conexión']) . "\n\n";
                if (ob_get_level()) ob_flush();
                flush();
                break;
            }

            // Verificar si cliente aún está conectado
            if (connection_aborted()) {
                break;
            }
        }
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
     * Endpoint para actualizaciones push desde IA (interno).
     * POST /api/ia/sse-update
     */
    public function sseUpdate(Request $request)
    {
        // Validar secret
        $secret = $request->header('Authorization', '');
        $expectedSecret = 'Bearer ' . config('audio.ia.callback_secret');

        if ($secret !== $expectedSecret) {
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
