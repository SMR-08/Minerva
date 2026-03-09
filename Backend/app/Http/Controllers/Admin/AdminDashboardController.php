<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Models\Transcripcion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AdminDashboardController extends Controller
{
    /**
     * Dashboard Principal con estadísticas.
     */
    public function index()
    {
        // Estadísticas Reales
        $stats = [
            'total_users' => Usuario::count(),
            'total_transcriptions' => Transcripcion::count(),
            'storage_used' => $this->getStorageSize(),
            'ai_queue' => $this->getAIQueueStatus(),
        ];

        return view('admin.dashboard', compact('stats'));
    }

    /**
     * Consola de Debug para pruebas de IA y Backend.
     */
    public function debug()
    {
        return view('admin.debug');
    }

    /**
     * Prueba la conexión con el microservicio de IA.
     */
    public function testIA()
    {
        try {
            $urlIA = config('services.ai_service.url');
            $respuesta = Http::timeout(config('services.ai_service.timeout', 120))
                             ->get("$urlIA/estado");

            if ($respuesta->successful()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Conexión exitosa con el microservicio de IA.',
                    'data' => $respuesta->json()
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'La IA respondió con error: ' . $respuesta->status()
            ], 502);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Fallo la conexión a la IA: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Subir audio y enviarlo al microservicio de IA.
     */
    public function uploadAudio(Request $request)
    {
        $request->validate([
            'audio' => 'required|file|mimes:mp3,wav,m4a,flac,ogg|max:51200', // max 50MB
        ]);

        try {
            $audio = $request->file('audio');
            $inputPath = trim(config('services.ai_service.input_path', 'AI_Input'), '"\'');
            $absolutePath = str_starts_with($inputPath, '/') ? $inputPath : base_path($inputPath);

            $fileName = time() . '_' . $audio->getClientOriginalName();
            $audio->move($absolutePath, $fileName);

            $urlIA = config('services.ai_service.url');
            $respuesta = Http::timeout(config('services.ai_service.timeout', 120))
                ->post("$urlIA/transcribir_diarizado", [
                    'nombre_archivo' => $fileName,
                ]);

            if ($respuesta->successful()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Audio subido y enviado a procesar.',
                    'data' => $respuesta->json()
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'La IA respondió con error: ' . $respuesta->status(),
                'details' => $respuesta->body()
            ], 502);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al procesar subida: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getStorageSize()
    {
        // Cálculo básico de espacio en AI_Input (puedes ajustarlo)
        $inputPath = trim(config('services.ai_service.input_path', 'AI_Input'), '"\'');
        $path = str_starts_with($inputPath, '/') ? $inputPath : base_path($inputPath);
        
        if (!file_exists($path)) return '0 MB';
        
        $size = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path)) as $file) {
            $size += $file->getSize();
        }
        
        return round($size / 1024 / 1024, 2) . ' MB';
    }

    private function getAIQueueStatus()
    {
        try {
            $urlIA = config('services.ai_service.url');
            $res = Http::timeout(3)->get("$urlIA/estado_cola");
            if ($res->successful()) {
                $data = $res->json();
                return $data['cola_espera'] ?? 0;
            }
        } catch (\Exception $e) {}
        return 'N/A';
    }
}
