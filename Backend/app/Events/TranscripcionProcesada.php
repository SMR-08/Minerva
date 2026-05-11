<?php

namespace App\Events;

use App\Models\Transcripcion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se dispara cuando una transcripción completa su procesamiento
 * (ya sea COMPLETADO o FALLIDO), tras recibir el callback de la IA.
 */
class TranscripcionProcesada
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Transcripcion $transcripcion,
    ) {}
}
