<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // POST /api/register
    public function register(Request $peticion)
    {
        $peticion->validate([
            'nombre_completo' => 'required|string|max:100',
            'email' => 'required|email|unique:usuarios,email',
            'password' => 'required|string|min:6',
            'device_name' => 'required|string',
        ]);

        $usuario = Usuario::create([
            'nombre_completo' => $peticion->nombre_completo,
            'email' => $peticion->email,
            'password_hash' => Hash::make($peticion->password),
            'id_rol' => 2, // 2 = USUARIO (Por defecto)
            'id_estado' => 1, // 1 = ACTIVO
            'ultimo_acceso' => now(),
        ]);

        // Auto-login al registrarse
        $token = $usuario->createToken($peticion->device_name)->plainTextToken;

        return response()->json([
            'message' => 'Usuario registrado correctamente',
            'token' => $token,
            'usuario' => [
                'id' => $usuario->id_usuario,
                'nombre' => $usuario->nombre_completo,
                'email' => $usuario->email,
                'rol' => 'USUARIO',
            ]
        ], 201);
    }

    // POST /api/login
    public function login(Request $peticion)
    {
        $peticion->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required',
        ]);

        $usuario = Usuario::where('email', $peticion->email)->first();

        if (! $usuario || ! Hash::check($peticion->password, $usuario->password_hash)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        // Verificar estado
        if ($usuario->id_estado !== 1) { // 1 = ACTIVO
            throw ValidationException::withMessages([
                'email' => ['Tu cuenta no está activa.'],
            ]);
        }

        // Actualizar último acceso
        $usuario->ultimo_acceso = now();
        $usuario->save();

        // Crear token
        $token = $usuario->createToken($peticion->device_name)->plainTextToken;

        return response()->json([
            'token' => $token,
            'usuario' => [
                'id' => $usuario->id_usuario,
                'nombre' => $usuario->nombre_completo,
                'email' => $usuario->email,
                'rol' => $usuario->rol->nombre ?? 'USUARIO',
            ]
        ]);
    }

    // POST /api/logout
    public function logout(Request $peticion)
    {
        $peticion->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    // GET /api/user
    public function user(Request $peticion) 
    {
        return $peticion->user()->load('rol');
    }
}
