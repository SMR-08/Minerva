<?php

namespace App\Console\Commands;

use App\Models\Transcripcion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgeStaleTranscriptions extends Command
{
    protected $signature = 'minerva:purge-stale-transcriptions';
    protected $description = 'Marca como FALLIDO las transcripciones en estado SUBIENDO con mas de 24h de antiguedad';

    public function handle(): int
    {
        $stale = Transcripcion::where('estado', 'SUBIENDO')
            ->where('fecha_grabacion', '<', now()->subHours(24))
            ->get();

        $count = $stale->count();

        if ($count === 0) {
            $this->info('No se encontraron transcripciones huerfanas.');
            return self::SUCCESS;
        }

        foreach ($stale as $transcripcion) {
            $transcripcion->update([
                'estado' => 'FALLIDO',
                'error_mensaje' => 'Transcripcion abandonada: timeout de subida excedido',
            ]);
        }

        Log::warning("Se limpiaron {$count} transcripciones huerfanas en estado SUBIENDO con mas de 24h.");
        $this->info("Se limpiaron {$count} transcripciones huerfanas.");

        return self::SUCCESS;
    }
}
