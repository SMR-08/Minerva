<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TagResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_tag' => $this->id_tag,
            'nombre' => $this->nombre,
            'color_hex' => $this->color_hex,
            'id_usuario' => $this->id_usuario,
        ];
    }
}
