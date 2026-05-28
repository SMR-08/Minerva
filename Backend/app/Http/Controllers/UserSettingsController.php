<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangeUserPasswordRequest;
use App\Http\Requests\UpdateUserProfileRequest;
use App\Http\Resources\UsuarioResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserSettingsController extends Controller
{
    public function updateProfile(UpdateUserProfileRequest $peticion)
    {
        $usuario = $peticion->user();
        $usuario->update($peticion->validated());

        return response()->json([
            'message' => 'Perfil actualizado correctamente',
            'usuario' => new UsuarioResource($usuario->refresh()),
        ]);
    }

    public function changePassword(ChangeUserPasswordRequest $peticion)
    {
        $usuario = $peticion->user();

        if (! Hash::check($peticion->password_actual, $usuario->password_hash)) {
            throw ValidationException::withMessages([
                'password_actual' => ['La contraseña actual no es correcta.'],
            ]);
        }

        $usuario->password_hash = Hash::make($peticion->password_nuevo);
        $usuario->save();

        return response()->json([
            'message' => 'Contraseña actualizada correctamente',
        ]);
    }

    public function destroy(Request $peticion)
    {
        $usuario = $peticion->user();

        $usuario->tokens()->delete();
        $usuario->delete();

        return response()->json([
            'message' => 'Cuenta eliminada correctamente',
        ]);
    }
}
