<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tema extends Model
{
    use HasFactory;

    protected $table = 'temas';
    protected $primaryKey = 'id_tema';
    public $timestamps = false;

    protected $fillable = ['id_asignatura', 'nombre', 'orden'];

    public function asignatura()
    {
        return $this->belongsTo(Asignatura::class, 'id_asignatura');
    }

    public function transcripciones()
    {
        return $this->hasMany(Transcripcion::class, 'id_tema');
    }
}
