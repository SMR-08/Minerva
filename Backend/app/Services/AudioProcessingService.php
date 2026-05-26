<?php

namespace App\Services;

use App\Jobs\AudioProcessingJob;
use App\Events\TranscripcionProcesada;
use App\Models\Tema;
use App\Models\Transcripcion;
use App\Models\Usuario;
use App\Support\Debug;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AudioProcessingService
{
    public function procesarAudio(UploadedFile $file, string $idTema, Usuario $usuario, array $datos): Transcripcion
    {
        $tema = Tema::where('id_tema', $idTema)
            ->whereHas('asignatura', function ($consulta) use ($usuario) {
                $consulta->where('id_usuario', $usuario->id_usuario);
            })
            ->firstOrFail();

        $uuid = Str::uuid()->toString();

        $titulo = $datos['titulo']
            ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        Debug::audio("Archivo recibido", [
            'trace_id' => $uuid,
            'size_mb' => round($file->getSize() / 1048576, 2),
            'format' => $file->getClientOriginalExtension(),
            'original' => $file->getClientOriginalName(),
            'tema_id' => $idTema,
            'user_id' => $usuario->id_usuario,
        ]);

        $transcripcion = Transcripcion::create([
            'id_tema' => $tema->id_tema,
            'uuid_referencia' => $uuid,
            'estado' => 'SUBIENDO',
            'nombre_archivo_original' => $file->getClientOriginalName(),
            'titulo' => $titulo,
            'duracion_segundos' => 0,
            'progreso_porcentaje' => 0,
        ]);

        $ruta = $file->store('uploads');

        Debug::audio("Almacenado en storage", [
            'trace_id' => $uuid,
            'path' => $ruta,
            'disk' => 'local',
        ]);

        $transcripcion->update([
            'estado' => 'ENCOLADO',
        ]);

        $idioma = $datos['idioma'] ?? 'auto';

        Debug::queue("Job dispatched", [
            'trace_id' => $uuid,
            'queue' => 'process_audio',
            'idioma' => $idioma,
            'ruta' => $ruta,
        ]);

        AudioProcessingJob::dispatch($transcripcion, $ruta, $idioma)
            ->onQueue('process_audio');

        return $transcripcion;
    }

    public function procesarCallback(string $uuid, array $payload): Transcripcion
    {
        $transcripcion = Transcripcion::where('uuid_referencia', $uuid)->firstOrFail();

        if ($payload['estado'] === 'COMPLETADO') {
            $resultado = $payload['resultado'];

            $nombreProfesor = $transcripcion->tema?->asignatura?->profesor;
            $transcripcionArray = $resultado['transcripcion'];
            if ($nombreProfesor) {
                foreach ($transcripcionArray as &$segmento) {
                    if (($segmento['hablante'] ?? '') === 'Profesor') {
                        $segmento['hablante'] = $nombreProfesor;
                    }
                }
                unset($segmento);
            }

            $transcripcion->update([
                'estado' => 'COMPLETADO',
                'progreso_porcentaje' => 100,
                'etapa_actual' => null,
                'texto_plano' => collect($transcripcionArray)->pluck('texto')->join("\n"),
                'texto_diarizado' => $transcripcionArray,
                'duracion_segundos' => $resultado['metricas_rendimiento']['duracion_audio_segundos'] ?? 0,
                'fecha_procesamiento' => now(),
            ]);

            Log::channel('structured')->info("Transcripcion completada", [
                'trace_id' => $uuid,
                'service' => 'laravel',
                'duration_audio_s' => $resultado['metricas_rendimiento']['duracion_audio_segundos'] ?? 0,
                'processing_time_s' => $resultado['metricas_rendimiento']['tiempo_procesamiento_total_segundos'] ?? 0,
            ]);

            TranscripcionProcesada::dispatch($transcripcion);
        } elseif ($payload['estado'] === 'PROCESANDO') {
            $resultado = $payload['resultado'] ?? [];
            $transcripcion->update([
                'estado' => 'PROCESANDO',
                'progreso_porcentaje' => $resultado['progreso'] ?? null,
                'etapa_actual' => $resultado['etapa'] ?? null,
            ]);
        } elseif ($payload['estado'] === 'RESUMIENDO') {
            $transcripcion->update([
                'estado' => 'RESUMIENDO',
                'etapa_actual' => 'RESUMEN',
            ]);
        } elseif ($payload['estado'] === 'LISTO') {
            $transcripcion->update([
                'estado' => 'LISTO',
                'resumen_ia' => $payload['resumen'] ?? null,
                'etapa_actual' => null,
            ]);

            Log::channel('structured')->info("Resumen completado", [
                'trace_id' => $uuid,
                'service' => 'laravel',
                'resumen_length' => strlen($payload['resumen'] ?? ''),
            ]);

            TranscripcionProcesada::dispatch($transcripcion);
        } else {
            $transcripcion->update([
                'estado' => 'FALLIDO',
                'error_mensaje' => $payload['error'] ?? 'Error desconocido',
            ]);

            Log::channel('structured')->error("Transcripcion fallida", [
                'trace_id' => $uuid,
                'service' => 'laravel',
                'error' => $payload['error'] ?? 'Error desconocido',
            ]);

            TranscripcionProcesada::dispatch($transcripcion);
        }

        return $transcripcion;
    }
}
