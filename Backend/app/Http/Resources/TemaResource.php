<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_tema' => $this->id_tema,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'orden' => $this->orden,
            'id_asignatura' => $this->id_asignatura,
            'asignatura' => new AsignaturaResource($this->whenLoaded('asignatura')),
            'num_transcripciones' => $this->transcripciones()->count(),
        ];
    }
}
