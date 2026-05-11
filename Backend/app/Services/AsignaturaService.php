<?php

namespace App\Services;

use App\Models\Asignatura;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Collection;

class AsignaturaService
{
    public function listarPorUsuario(Usuario $usuario): Collection
    {
        return Asignatura::where('id_usuario', $usuario->id_usuario)->get();
    }

    public function crear(array $datos, Usuario $usuario): Asignatura
    {
        return Asignatura::create(array_merge($datos, [
            'id_usuario' => $usuario->id_usuario,
        ]));
    }

    public function actualizar(Asignatura $asignatura, array $datos): Asignatura
    {
        $asignatura->update($datos);
        return $asignatura;
    }

    public function eliminar(Asignatura $asignatura): void
    {
        $asignatura->delete();
    }
}
