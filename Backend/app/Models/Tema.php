<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tema extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'temas';
    protected $primaryKey = 'id_tema';
    // $timestamps = false porque la tabla no tiene columnas 'created_at' ni 'updated_at'
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
