<?php

namespace App\Listeners;

use App\Events\TranscripcionProcesada;
use Illuminate\Support\Facades\Log;

/**
 * Registra en el log cuando una transcripción termina de procesarse.
 * Punto de extensión para notificaciones futuras (email, push, etc.).
 */
class RegistrarTranscripcionCompletada
{
    public function handle(TranscripcionProcesada $event): void
    {
        $t = $event->transcripcion;

        Log::info('Transcripción procesada', [
            'uuid' => $t->uuid_referencia,
            'estado' => $t->estado,
            'titulo' => $t->titulo,
            'duracion_segundos' => $t->duracion_segundos,
        ]);
    }
}
