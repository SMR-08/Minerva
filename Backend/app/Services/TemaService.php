<?php

namespace App\Services;

use App\Models\Asignatura;
use App\Models\Tema;
use Illuminate\Database\Eloquent\Collection;

class TemaService
{
    public function listarPorAsignatura(Asignatura $asignatura): Collection
    {
        return Tema::where('id_asignatura', $asignatura->id_asignatura)
            ->orderBy('orden', 'asc')
            ->get();
    }

    public function crear(array $datos, Asignatura $asignatura): Tema
    {
        return Tema::create(array_merge($datos, [
            'id_asignatura' => $asignatura->id_asignatura,
        ]));
    }

    public function actualizar(Tema $tema, array $datos): Tema
    {
        $tema->update($datos);
        return $tema;
    }

    public function eliminar(Tema $tema): void
    {
        $tema->delete();
    }
}
