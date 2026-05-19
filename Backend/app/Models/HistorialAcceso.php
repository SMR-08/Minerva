<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistorialAcceso extends Model
{
    protected $table = 'historial_accesos';
    protected $primaryKey = 'id_acceso';
    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'ip_acceso',
        'user_agent',
    ];

    protected $casts = [
        'fecha_acceso' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }
}
