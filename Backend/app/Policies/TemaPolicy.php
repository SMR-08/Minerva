<?php

namespace App\Policies;

use App\Models\Tema;
use App\Models\Usuario;
use Illuminate\Auth\Access\Response;

class TemaPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(Usuario $usuario): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Usuario $usuario, Tema $tema): bool
    {
        return $usuario->id_usuario === $tema->asignatura->id_usuario;
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
    public function update(Usuario $usuario, Tema $tema): bool
    {
        return $usuario->id_usuario === $tema->asignatura->id_usuario;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Usuario $usuario, Tema $tema): bool
    {
        return $usuario->id_usuario === $tema->asignatura->id_usuario;
    }
}
