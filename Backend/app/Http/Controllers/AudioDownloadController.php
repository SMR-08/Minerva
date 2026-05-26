<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Endpoint interno para que la IA descargue archivos de audio.
 * Autenticado con IA_CALLBACK_SECRET (mismo que callbacks).
 * Patrón "patata caliente": sirve el archivo y lo elimina.
 */
class AudioDownloadController extends Controller
{
    public function download(Request $request, string $uuid): BinaryFileResponse
    {
        // Verificar secret
        $secret = str_replace('Bearer ', '', $request->header('Authorization', ''));
        if ($secret !== config('audio.ia.callback_secret')) {
            abort(401, 'No autorizado');
        }

        // Buscar archivo (puede ser mp3, wav, ogg, m4a, mp4)
        $filePath = null;

        foreach (['mp3', 'wav', 'ogg', 'm4a', 'mp4', 'webm'] as $ext) {
            $storagePath = "temp-audio/{$uuid}.{$ext}";
            if (Storage::exists($storagePath)) {
                $filePath = Storage::path($storagePath);
                break;
            }
        }

        if (!$filePath) {
            Log::channel('structured')->warning('Audio no encontrado para descarga', [
                'service' => 'laravel',
                'trace_id' => $uuid,
            ]);
            abort(404, 'Archivo no encontrado o ya descargado');
        }

        Log::channel('structured')->info('Audio servido a IA', [
            'service' => 'laravel',
            'trace_id' => $uuid,
            'size_mb' => round(filesize($filePath) / 1048576, 2),
        ]);

        // Servir y eliminar (patata caliente)
        return response()->download($filePath)->deleteFileAfterSend(true);
    }
}
