<?php

namespace App\Http\Controllers;

use App\Models\Asignatura;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AsignaturaController extends Controller
{
    /**
     * Muestra un listado del recurso.
     * GET /api/asignaturas
     */
    public function index(Request $peticion)
    {
        // Usuarios solo ven sus asignaturas
        // Admin ve todas? (Por ahora asumimos contexto de usuario)
        $usuario = $peticion->user();
        
        $consulta = Asignatura::where('id_usuario', $usuario->id_usuario);

        return response()->json($consulta->get());
    }

    /**
     * Almacena un recurso recién creado en el almacenamiento.
     * POST /api/asignaturas
     */
    public function store(Request $peticion)
    {
        $peticion->validate([
            'nombre' => 'required|string|max:100',
            'profesor' => 'nullable|string|max:100',
            'descripcion' => 'nullable|string',
            'color_hex' => 'nullable|string|size:7',
        ]);

        $asignatura = Asignatura::create([
            'id_usuario' => $peticion->user()->id_usuario,
            'nombre' => $peticion->nombre,
            'profesor' => $peticion->profesor,
            'descripcion' => $peticion->descripcion,
            'color_hex' => $peticion->color_hex ?? '#3B82F6',
        ]);

        return response()->json($asignatura, 201);
    }

    /**
     * Muestra el recurso especificado.
     * GET /api/asignaturas/{id}
     */
    public function show(string $id)
    {
        $asignatura = Asignatura::where('id_asignatura', $id)
            ->where('id_usuario', Auth::user()->id_usuario)
            ->firstOrFail();

        return response()->json($asignatura);
    }

    /**
     * Actualiza el recurso especificado en el almacenamiento.
     * PUT /api/asignaturas/{id}
     */
    public function update(Request $peticion, string $id)
    {
        $asignatura = Asignatura::where('id_asignatura', $id)
            ->where('id_usuario', Auth::user()->id_usuario)
            ->firstOrFail();

        $peticion->validate([
            'nombre' => 'string|max:100',
            'profesor' => 'nullable|string|max:100',
            'descripcion' => 'nullable|string',
            'color_hex' => 'nullable|string|size:7',
        ]);

        $asignatura->update($peticion->all());

        return response()->json($asignatura);
    }

    /**
     * Elimina el recurso especificado del almacenamiento.
     * DELETE /api/asignaturas/{id}
     */
    public function destroy(string $id)
    {
        $asignatura = Asignatura::where('id_asignatura', $id)
            ->where('id_usuario', Auth::user()->id_usuario)
            ->firstOrFail();

        $asignatura->delete();

        return response()->json(['message' => 'Asignatura eliminada correctamente']);
    }
}
