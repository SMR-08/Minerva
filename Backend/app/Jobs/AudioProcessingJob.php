<?php

namespace App\Jobs;

use App\Models\Transcripcion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AudioProcessingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 7200; // 2 horas para audios largos
    public $backoff = [60, 300, 900]; // 1min, 5min, 15min entre reintentos

    protected Transcripcion $transcripcion;
    protected string $idioma;
    protected bool $activar_asignacion_roles;

    /**
     * Create a new job instance.
     */
    public function __construct(
        Transcripcion $transcripcion,
        string $idioma = 'auto',
        bool $activar_asignacion_roles = true
    ) {
        $this->transcripcion = $transcripcion;
        $this->idioma = $idioma;
        $this->activar_asignacion_roles = $activar_asignacion_roles;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $uuid = $this->transcripcion->uuid_referencia;

        try {
            // Actualizar estado a PROCESANDO
            $this->transcripcion->update([
                'estado' => 'PROCESANDO',
                'etapa_actual' => 'INICIANDO',
                'progreso_porcentaje' => 0,
            ]);

            // Obtener configuración de IA
            $urlIA = config('audio.ia.upload_url', env('IA_UPLOAD_URL'));
            $callbackUrl = route('ia.callback', ['secret' => config('audio.ia.callback_secret')]);

            // Enviar a IA para procesamiento
            $response = Http::timeout($this->timeout)->post("{$urlIA}/process", [
                'uuid' => $uuid,
                'idioma' => $this->idioma,
                'activar_asignacion_roles' => $this->activar_asignacion_roles,
                'callback_url' => $callbackUrl,
            ]);

            if ($response->successful()) {
                // IA aceptó el trabajo - esperar callback con resultado
                Log::info("Audio {$uuid} enviado a IA para procesamiento");
            } else {
                throw new \Exception("IA rechazó el procesamiento: " . $response->status());
            }

        } catch (\Exception $e) {
            Log::error("Error en AudioProcessingJob para {$uuid}: " . $e->getMessage());

            // Actualizar transcripción con error
            $this->transcripcion->update([
                'estado' => 'FALLIDO',
                'error_mensaje' => $e->getMessage(),
                'intentos' => $this->transcripcion->intentos + 1,
            ]);

            throw $e; // Para que Laravel maneje los reintentos
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("AudioProcessingJob fallido definitivamente para {$this->transcripcion->uuid_referencia}");

        $this->transcripcion->update([
            'estado' => 'FALLIDO',
            'error_mensaje' => 'Error después de ' . $this->transcripcion->intentos . ' intentos: ' . $exception->getMessage(),
        ]);
    }
}
