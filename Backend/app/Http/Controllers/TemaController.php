<?php

namespace App\Http\Controllers;

use App\Models\Tema;
use App\Models\Asignatura;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TemaController extends Controller
{
    /**
     * Muestra un listado del recurso.
     * GET /api/temas?asignatura_id=X
     */
    public function index(Request $peticion)
    {
        $peticion->validate([
            'asignatura_id' => 'required|exists:asignaturas,id_asignatura',
        ]);

        // Verificar que la asignatura pertenece al usuario
        $asignatura = Asignatura::where('id_asignatura', $peticion->asignatura_id)
            ->where('id_usuario', Auth::user()->id_usuario)
            ->firstOrFail();

        $temas = Tema::where('id_asignatura', $asignatura->id_asignatura)
            ->orderBy('orden', 'asc')
            ->get();

        return response()->json($temas);
    }

    /**
     * Almacena un recurso recién creado en el almacenamiento.
     * POST /api/temas
     */
    public function store(Request $peticion)
    {
        $peticion->validate([
            'id_asignatura' => 'required|exists:asignaturas,id_asignatura',
            'nombre' => 'required|string|max:100',
            'orden' => 'integer',
        ]);

        // Verificar propiedad de la asignatura
        $asignatura = Asignatura::where('id_asignatura', $peticion->id_asignatura)
            ->where('id_usuario', Auth::user()->id_usuario)
            ->firstOrFail();

        $tema = Tema::create([
            'id_asignatura' => $peticion->id_asignatura,
            'nombre' => $peticion->nombre,
            'orden' => $peticion->orden ?? 0,
        ]);

        return response()->json($tema, 201);
    }

    /**
     * Muestra el recurso especificado.
     * GET /api/temas/{id}
     */
    public function show(string $id)
    {
        // Join para verificar que la asignatura del tema pertenece al usuario
        $tema = Tema::where('id_tema', $id)
            ->whereHas('asignatura', function ($consulta) {
                $consulta->where('id_usuario', Auth::user()->id_usuario);
            })
            ->firstOrFail();

        return response()->json($tema);
    }

    /**
     * Actualiza el recurso especificado en el almacenamiento.
     * PUT /api/temas/{id}
     */
    public function update(Request $peticion, string $id)
    {
        $tema = Tema::where('id_tema', $id)
            ->whereHas('asignatura', function ($consulta) {
                $consulta->where('id_usuario', Auth::user()->id_usuario);
            })
            ->firstOrFail();

        $peticion->validate([
            'nombre' => 'string|max:100',
            'orden' => 'integer',
        ]);

        $tema->update($peticion->only(['nombre', 'orden']));

        return response()->json($tema);
    }

    /**
     * Elimina el recurso especificado del almacenamiento.
     * DELETE /api/temas/{id}
     */
    public function destroy(string $id)
    {
        $tema = Tema::where('id_tema', $id)
            ->whereHas('asignatura', function ($consulta) {
                $consulta->where('id_usuario', Auth::user()->id_usuario);
            })
            ->firstOrFail();

        $tema->delete();

        return response()->json(['message' => 'Tema eliminado correctamente']);
    }
}
