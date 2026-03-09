<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asignatura extends Model
{
    use HasFactory;

    protected $table = 'asignaturas';
    protected $primaryKey = 'id_asignatura';
    public $timestamps = false; // Using fecha_creacion standard

    protected $fillable = ['id_usuario', 'nombre', 'profesor', 'descripcion', 'color_hex'];

    protected $casts = [
        'fecha_creacion' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }

    public function temas()
    {
        return $this->hasMany(Tema::class, 'id_asignatura');
    }
}
