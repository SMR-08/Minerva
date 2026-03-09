<?php

namespace App\Policies;

use App\Models\Asignatura;
use App\Models\Usuario;
use Illuminate\Auth\Access\Response;

class AsignaturaPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(Usuario $usuario): bool
    {
        return true; // Controlado en el index del controlador
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Usuario $usuario, Asignatura $asignatura): bool
    {
        return $usuario->id_usuario === $asignatura->id_usuario;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Usuario $usuario): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Usuario $usuario, Asignatura $asignatura): bool
    {
        return $usuario->id_usuario === $asignatura->id_usuario;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Usuario $usuario, Asignatura $asignatura): bool
    {
        return $usuario->id_usuario === $asignatura->id_usuario;
    }
}
