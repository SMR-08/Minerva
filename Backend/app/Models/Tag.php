<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;

    protected $table = 'tags';
    protected $primaryKey = 'id_tag';
    public $timestamps = false;

    protected $fillable = ['id_usuario', 'nombre', 'color_hex'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }

    public function transcripciones()
    {
        return $this->belongsToMany(Transcripcion::class, 'transcripciones_tags', 'id_tag', 'id_transcripcion');
    }
}
