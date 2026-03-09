<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transcripcion extends Model
{
    use HasFactory;

    protected $table = 'transcripciones';
    protected $primaryKey = 'id_transcripcion';
    public $timestamps = false;

    protected $fillable = [
        'id_tema', 
        'uuid_referencia', 
        'nombre_archivo_original', 
        'duracion_segundos', 
        'titulo', 
        'texto_plano', 
        'texto_diarizado', 
        'resumen_ia', 
        'mapa_mental_mermaid'
    ];

    protected $casts = [
        'texto_diarizado' => 'array', // Auto-JSON decode
        'fecha_grabacion' => 'datetime',
        'fecha_procesamiento' => 'datetime',
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
