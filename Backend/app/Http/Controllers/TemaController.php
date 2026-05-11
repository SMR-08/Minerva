<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTemaRequest;
use App\Http\Requests\UpdateTemaRequest;
use App\Http\Resources\TemaResource;
use App\Models\Asignatura;
use App\Models\Tema;
use App\Services\TemaService;
use Illuminate\Http\Request;

class TemaController extends Controller
{
    public function __construct(
        private TemaService $temaService,
    ) {}

    public function index(Request $peticion)
    {
        $peticion->validate([
            'asignatura_id' => 'required|exists:asignaturas,id_asignatura',
        ]);

        $asignatura = Asignatura::findOrFail($peticion->asignatura_id);
        $this->authorize('view', $asignatura);

        return TemaResource::collection(
            $this->temaService->listarPorAsignatura($asignatura)
        );
    }

    public function store(StoreTemaRequest $peticion)
    {
        $peticion->validate([
            'id_asignatura' => 'required|exists:asignaturas,id_asignatura',
        ]);

        $asignatura = Asignatura::findOrFail($peticion->id_asignatura);
        $this->authorize('view', $asignatura);

        $tema = $this->temaService->crear($peticion->validated(), $asignatura);

        return new TemaResource($tema);
    }

    public function show(Tema $tema)
    {
        $this->authorize('view', $tema);

        return new TemaResource($tema);
    }

    public function update(UpdateTemaRequest $peticion, Tema $tema)
    {
        $this->authorize('update', $tema);

        $tema = $this->temaService->actualizar($tema, $peticion->validated());

        return new TemaResource($tema);
    }

    public function destroy(Tema $tema)
    {
        $this->authorize('delete', $tema);

        $this->temaService->eliminar($tema);

        return response()->json(['message' => 'Tema eliminado correctamente']);
    }
}
