<?php

namespace App\Services;

use App\Models\Transcripcion;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Collection;

class TranscripcionService
{
    public function listarPorUsuario(Usuario $usuario): Collection
    {
        return Transcripcion::whereHas('tema.asignatura', function ($query) use ($usuario) {
            $query->where('id_usuario', $usuario->id_usuario);
        })
            ->with(['tema.asignatura'])
            ->orderBy('fecha_procesamiento', 'desc')
            ->get();
    }

    public function obtenerPorIdYUsuario(int $id, Usuario $usuario): Transcripcion
    {
        return Transcripcion::where('id_transcripcion', $id)
            ->whereHas('tema.asignatura', function ($query) use ($usuario) {
                $query->where('id_usuario', $usuario->id_usuario);
            })
            ->with('tema.asignatura')
            ->firstOrFail();
    }

    public function actualizar(Transcripcion $transcripcion, array $datos): Transcripcion
    {
        $transcripcion->update($datos);
        return $transcripcion;
    }

    public function eliminar(Transcripcion $transcripcion): void
    {
        $transcripcion->delete();
    }
}
