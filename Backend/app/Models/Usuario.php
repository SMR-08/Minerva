<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Model
{
    use HasFactory, HasApiTokens;

    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';
    public $timestamps = false; // Manually managed in SQL for registration/access

    protected $fillable = [
        'id_rol', 
        'id_estado', 
        'email', 
        'password_hash', 
        'nombre_completo', 
        'ultimo_acceso'
    ];

    protected $hidden = [
        'password_hash',
    ];

    // Auth Password override
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function rol()
    {
        return $this->belongsTo(Rol::class, 'id_rol');
    }

    public function estado()
    {
        return $this->belongsTo(EstadoUsuario::class, 'id_estado');
    }

    public function asignaturas()
    {
        return $this->hasMany(Asignatura::class, 'id_usuario');
    }

    public function tags()
    {
        return $this->hasMany(Tag::class, 'id_usuario');
    }
}
