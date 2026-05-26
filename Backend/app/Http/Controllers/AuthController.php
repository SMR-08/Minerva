<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\HistorialAcceso;
use App\Models\Usuario;
use App\Support\Debug;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterUserRequest $peticion)
    {
        $usuario = Usuario::create([
            'nombre_completo' => $peticion->nombre_completo,
            'email' => $peticion->email,
            'password_hash' => Hash::make($peticion->password),
            'id_rol' => 2,
            'id_estado' => 1,
            'ultimo_acceso' => now(),
        ]);

        $token = $usuario->createToken($peticion->device_name ?? 'default')->plainTextToken;

        Debug::auth("Registro exitoso", [
            'user_id' => $usuario->id_usuario,
            'email' => $usuario->email,
        ]);

        Log::channel('structured')->info("Usuario registrado", [
            'service' => 'laravel',
            'user_id' => $usuario->id_usuario,
        ]);

        return response()->json([
            'message' => 'Usuario registrado correctamente',
            'token' => $token,
            'usuario' => new UsuarioResource($usuario),
        ], 201);
    }

    public function login(LoginRequest $peticion)
    {
        $usuario = Usuario::where('email', $peticion->email)->first();

        if (! $usuario || ! Hash::check($peticion->password, $usuario->password_hash)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        if ($usuario->id_estado !== 1) {
            throw ValidationException::withMessages([
                'email' => ['Tu cuenta no está activa.'],
            ]);
        }

        $usuario->ultimo_acceso = now();
        $usuario->save();

        HistorialAcceso::create([
            'id_usuario' => $usuario->id_usuario,
            'ip_acceso' => $peticion->ip(),
            'user_agent' => $peticion->userAgent(),
        ]);

        $token = $usuario->createToken($peticion->device_name ?? 'default')->plainTextToken;

        Debug::auth("Login exitoso", [
            'user_id' => $usuario->id_usuario,
            'ip' => $peticion->ip(),
        ]);

        Log::channel('structured')->info("Login", [
            'service' => 'laravel',
            'user_id' => $usuario->id_usuario,
        ]);

        return response()->json([
            'token' => $token,
            'usuario' => new UsuarioResource($usuario),
        ]);
    }

    public function logout(Request $peticion)
    {
        $peticion->user()->currentAccessToken()->delete();

        Debug::auth("Logout", [
            'user_id' => $peticion->user()->id_usuario,
        ]);

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    public function user(Request $peticion)
    {
        return new UsuarioResource($peticion->user()->load('rol'));
    }
}
