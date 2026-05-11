<?php

namespace App\Http\Controllers;

use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index(Request $peticion)
    {
        $tags = Tag::where('id_usuario', $peticion->user()->id_usuario)->get();

        return TagResource::collection($tags);
    }

    public function store(Request $peticion)
    {
        $peticion->validate([
            'nombre' => 'required|string|max:50',
            'color_hex' => 'nullable|string|size:7',
        ]);

        $tag = Tag::create([
            'id_usuario' => $peticion->user()->id_usuario,
            'nombre' => $peticion->nombre,
            'color_hex' => $peticion->color_hex ?? '#6B7280',
        ]);

        return new TagResource($tag);
    }

    public function destroy(Request $peticion, string $id)
    {
        $tag = Tag::where('id_tag', $id)
            ->where('id_usuario', $peticion->user()->id_usuario)
            ->firstOrFail();

        $tag->delete();

        return response()->json(['message' => 'Etiqueta eliminada correctamente']);
    }
}
