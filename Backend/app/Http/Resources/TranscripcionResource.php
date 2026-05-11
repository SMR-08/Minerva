<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TranscripcionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_transcripcion' => $this->id_transcripcion,
            'uuid_referencia' => $this->uuid_referencia,
            'estado' => $this->estado,
            'estado_resumen' => $this->estado_resumen,
            'progreso_porcentaje' => $this->progreso_porcentaje,
            'etapa_actual' => $this->etapa_actual,
            'nombre_archivo_original' => $this->nombre_archivo_original,
            'titulo' => $this->titulo,
            'duracion_segundos' => $this->duracion_segundos,
            'texto_plano' => $this->texto_plano,
            'texto_diarizado' => $this->texto_diarizado,
            'resumen_ia' => $this->resumen_ia,
            'mapa_mental_mermaid' => $this->mapa_mental_mermaid,
            'fecha_grabacion' => $this->fecha_grabacion,
            'fecha_procesamiento' => $this->fecha_procesamiento,
            'tema' => new TemaResource($this->whenLoaded('tema')),
        ];
    }
}
