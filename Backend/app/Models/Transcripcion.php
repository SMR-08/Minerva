<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transcripcion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'transcripciones';
    protected $primaryKey = 'id_transcripcion';
    // $timestamps = false porque la tabla usa 'fecha_grabacion' y 'fecha_procesamiento'
    // en vez de las columnas por defecto 'created_at' y 'updated_at'
    public $timestamps = false;

    protected $fillable = [
        'id_tema',
        'uuid_referencia',
        'estado',
        'progreso_porcentaje',
        'etapa_actual',
        'nombre_archivo_original',
        'duracion_segundos',
        'titulo',
        'texto_plano',
        'texto_diarizado',
        'resumen_ia',
        'mapa_mental_mermaid',
        'intentos',
        'error_mensaje'
    ];

    protected $casts = [
        'texto_diarizado' => 'array', // Auto-JSON decode
        'fecha_grabacion' => 'datetime',
        'fecha_procesamiento' => 'datetime',
        'progreso_porcentaje' => 'integer',
        'intentos' => 'integer',
    ];

    public function tema()
    {
        return $this->belongsTo(Tema::class, 'id_tema');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'transcripciones_tags', 'id_transcripcion', 'id_tag');
    }
}
