<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AsignaturaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_asignatura' => $this->id_asignatura,
            'nombre' => $this->nombre,
            'profesor' => $this->profesor,
            'color_hex' => $this->color_hex,
            'icono' => $this->icono,
            'descripcion' => $this->descripcion,
            'semestre' => $this->semestre,
            'num_temas' => $this->temas()->count(),
            'fecha_creacion' => $this->fecha_creacion,
        ];
    }
}
