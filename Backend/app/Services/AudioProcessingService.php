<?php

namespace App\Services;

use App\Jobs\AudioProcessingJob;
use App\Events\TranscripcionProcesada;
use App\Models\Tema;
use App\Models\Transcripcion;
use App\Models\Usuario;
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

        $transcripcion->update([
            'estado' => 'ENCOLADO',
        ]);

        $idioma = $datos['idioma'] ?? 'auto';

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

            Log::info("Transcripción {$uuid} completada exitosamente");
            TranscripcionProcesada::dispatch($transcripcion);
        } else {
            $transcripcion->update([
                'estado' => 'FALLIDO',
                'error_mensaje' => $payload['error'],
            ]);

            Log::error("Transcripción {$uuid} fallida: " . $payload['error']);
            TranscripcionProcesada::dispatch($transcripcion);
        }

        return $transcripcion;
    }
}
