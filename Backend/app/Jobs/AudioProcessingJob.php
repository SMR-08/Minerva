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
use Illuminate\Support\Facades\Storage;

class AudioProcessingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 7200;
    public $backoff = [60, 300, 900];
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
        $intento = $this->attempts();

        Log::info("AudioProcessingJob intento {$intento}/{$this->tries} para {$uuid}");

        // Si ya falló demasiadas veces, marcar como FALLIDO directamente
        if ($intento > $this->tries) {
            Log::warning("Job {$uuid} excedió {$this->tries} intentos, marcando como FALLIDO");
            $this->transcripcion->update([
                'estado' => 'FALLIDO',
                'error_mensaje' => 'IA no disponible tras ' . $this->tries . ' intentos',
            ]);
            $this->delete();
            return;
        }

        $this->transcripcion->update([
            'estado' => 'PROCESANDO',
            'etapa_actual' => 'INICIANDO',
            'progreso_porcentaje' => 0,
        ]);

        $urlIA = config('audio.ia.upload_url');
        $callbackUrl = route('ia.callback');
        $timeout = config('audio.ia.timeout', 7200);

        try {
            if (!Storage::exists($this->rutaArchivo)) {
                throw new \Exception('Archivo de audio no encontrado: ' . $this->rutaArchivo);
            }

            $stream = Storage::readStream($this->rutaArchivo);

            if (!is_resource($stream)) {
                throw new \Exception('No se pudo abrir stream del archivo: ' . $this->rutaArchivo);
            }

            $response = Http::timeout($timeout)
                ->withHeaders(['X-Callback-Secret' => config('audio.ia.callback_secret')])
                ->attach(
                    'audio',
                    $stream,
                    $uuid . '.' . pathinfo($this->rutaArchivo, PATHINFO_EXTENSION)
                )
                ->post("{$urlIA}/upload", [
                    'uuid' => $uuid,
                    'idioma' => $this->idioma,
                    'callback_url' => $callbackUrl,
                ]);

            if (is_resource($stream)) {
                fclose($stream);
            }

            if (!$response->successful()) {
                throw new \Exception('IA rechazó el procesamiento: HTTP ' . $response->status());
            }

            Log::info("Audio {$uuid} enviado a IA exitosamente");

        } catch (\Exception $e) {
            Log::error("AudioProcessingJob error para {$uuid}: " . $e->getMessage());

            // Si es el último intento, fallar definitivamente
            if ($intento >= $this->tries) {
                $this->transcripcion->update([
                    'estado' => 'FALLIDO',
                    'error_mensaje' => 'Error tras ' . $intento . ' intentos: ' . $e->getMessage(),
                ]);
                $this->delete();
                return;
            }

            throw $e; // Re-lanzar para que Laravel reintente
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("AudioProcessingJob fallido definitivamente para {$this->transcripcion->uuid_referencia}");

        $this->transcripcion->update([
            'estado' => 'FALLIDO',
            'error_mensaje' => 'Error después de ' . $this->attempts() . ' intentos: ' . $exception->getMessage(),
        ]);

        if (Storage::exists($this->rutaArchivo)) {
            Storage::delete($this->rutaArchivo);
        }
    }
}
