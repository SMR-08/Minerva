<?php

namespace App\Jobs;

use App\Models\Transcripcion;
use App\Support\Debug;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

/**
 * Encola una tarea de transcripción en la cola unificada (Redis).
 *
 * Este job NO procesa audio. Solo:
 * 1. Mueve el archivo a temp-audio/ (accesible por el endpoint de descarga)
 * 2. Hace RPUSH a minerva_tasks con la metadata
 *
 * La IA consume de minerva_tasks via BRPOP y descarga el audio cuando le toca.
 */
class AudioProcessingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30; // Solo encola, no espera procesamiento
    public $backoff = [5, 15, 30];
    public $deleteWhenMissingModels = true;

    protected Transcripcion $transcripcion;
    protected string $rutaArchivo;
    protected string $idioma;

    public function __construct(
        Transcripcion $transcripcion,
        string $rutaArchivo,
        string $idioma = 'auto'
    ) {
        $this->transcripcion = $transcripcion;
        $this->rutaArchivo = $rutaArchivo;
        $this->idioma = $idioma;
    }

    public function handle(): void
    {
        $uuid = $this->transcripcion->uuid_referencia;

        Debug::queue("Encolando tarea en cola unificada", [
            'trace_id' => $uuid,
            'idioma' => $this->idioma,
        ]);

        // Mover archivo a temp-audio/ para el endpoint de descarga
        $extension = pathinfo($this->rutaArchivo, PATHINFO_EXTENSION) ?: 'mp3';
        $tempPath = "temp-audio/{$uuid}.{$extension}";

        // Asegurar que el directorio existe con permisos correctos (www-data)
        Storage::makeDirectory('temp-audio');

        if (Storage::exists($this->rutaArchivo) && !Storage::exists($tempPath)) {
            Storage::move($this->rutaArchivo, $tempPath);
        }

        if (!Storage::exists($tempPath)) {
            throw new \Exception("Archivo no encontrado: {$this->rutaArchivo} ni {$tempPath}");
        }

        // RPUSH a la cola unificada
        $tarea = json_encode([
            'type' => 'transcription',
            'uuid' => $uuid,
            'idioma' => $this->idioma,
            'audio_url' => url("/api/internal/audio-download/{$uuid}"),
            'callback_url' => route('ia.callback'),
            'created_at' => now()->toIso8601String(),
        ]);

        Redis::connection('ia')->rpush('minerva_tasks', $tarea);

        // Actualizar estado
        $this->transcripcion->update([
            'estado' => 'ENCOLADO',
            'etapa_actual' => 'EN_COLA',
            'progreso_porcentaje' => 0,
        ]);

        Log::channel('structured')->info("Tarea encolada en minerva_tasks", [
            'trace_id' => $uuid,
            'service' => 'worker',
            'type' => 'transcription',
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $uuid = $this->transcripcion->uuid_referencia;

        Log::channel('structured')->critical("AudioProcessingJob fallido", [
            'trace_id' => $uuid,
            'service' => 'worker',
            'error' => $exception->getMessage(),
        ]);

        $this->transcripcion->update([
            'estado' => 'FALLIDO',
            'error_mensaje' => 'Error al encolar: ' . $exception->getMessage(),
        ]);

        // Limpiar archivo temporal
        $extension = pathinfo($this->rutaArchivo, PATHINFO_EXTENSION) ?: 'mp3';
        $tempPath = "temp-audio/{$uuid}.{$extension}";
        if (Storage::exists($tempPath)) {
            Storage::delete($tempPath);
        }
        if (Storage::exists($this->rutaArchivo)) {
            Storage::delete($this->rutaArchivo);
        }
    }
}