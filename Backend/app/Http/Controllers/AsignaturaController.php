<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAsignaturaRequest;
use App\Http\Requests\UpdateAsignaturaRequest;
use App\Http\Resources\AsignaturaResource;
use App\Models\Asignatura;
use App\Services\AsignaturaService;

class AsignaturaController extends Controller
{
    public function __construct(
        private AsignaturaService $asignaturaService,
    ) {}

    public function index()
    {
        $this->authorize('viewAny', Asignatura::class);

        return AsignaturaResource::collection(
            $this->asignaturaService->listarPorUsuario(request()->user())
        );
    }

    public function store(StoreAsignaturaRequest $peticion)
    {
        $this->authorize('create', Asignatura::class);

        $asignatura = $this->asignaturaService->crear(
            $peticion->validated(),
            $peticion->user()
        );

        return new AsignaturaResource($asignatura);
    }

    public function show(Asignatura $asignatura)
    {
        $this->authorize('view', $asignatura);

        return new AsignaturaResource($asignatura);
    }

    public function update(UpdateAsignaturaRequest $peticion, Asignatura $asignatura)
    {
        $this->authorize('update', $asignatura);

        $asignatura = $this->asignaturaService->actualizar(
            $asignatura,
            $peticion->validated()
        );

        return new AsignaturaResource($asignatura);
    }

    public function destroy(Asignatura $asignatura)
    {
        $this->authorize('delete', $asignatura);

        $this->asignaturaService->eliminar($asignatura);

        return response()->json(['message' => 'Asignatura eliminada correctamente']);
    }
}
