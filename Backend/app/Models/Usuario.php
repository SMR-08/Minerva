<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasFactory, HasApiTokens;

    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';
    // $timestamps = false porque la tabla usa 'fecha_registro' y 'ultimo_acceso'
    // en vez de las columnas por defecto 'created_at' y 'updated_at'
    public $timestamps = false;

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
        'remember_token',
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
