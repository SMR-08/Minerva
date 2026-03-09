<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TagController extends Controller
{
    /**
     * Listar etiquetas del usuario autenticado.
     */
    public function index()
    {
        $tags = Tag::where('id_usuario', Auth::user()->id_usuario)->get();
        return response()->json($tags);
    }

    /**
     * Crear una nueva etiqueta.
     */
    public function store(Request $peticion)
    {
        $peticion->validate([
            'nombre' => 'required|string|max:50',
            'color_hex' => 'nullable|string|size:7',
        ]);

        $tag = Tag::create([
            'id_usuario' => Auth::user()->id_usuario,
            'nombre' => $peticion->nombre,
            'color_hex' => $peticion->color_hex ?? '#6B7280',
        ]);

        return response()->json($tag, 201);
    }

    /**
     * Eliminar una etiqueta.
     */
    public function destroy(string $id)
    {
        $tag = Tag::where('id_tag', $id)
            ->where('id_usuario', Auth::user()->id_usuario)
            ->firstOrFail();

        $tag->delete();

        return response()->json(['message' => 'Etiqueta eliminada correctamente']);
    }
}
