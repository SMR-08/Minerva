<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asignatura extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'asignaturas';
    protected $primaryKey = 'id_asignatura';
    // $timestamps = false porque la tabla usa 'fecha_creacion'
    // en vez de las columnas por defecto 'created_at' y 'updated_at'
    public $timestamps = false;

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
