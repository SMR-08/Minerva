<?php

use App\Models\Asignatura;
use App\Models\Tema;
use App\Models\Usuario;

if (!function_exists('authHeaders')) {
    function authHeaders(Usuario $usuario): array
    {
        $token = $usuario->createToken('test-token')->plainTextToken;
        return ['Authorization' => 'Bearer ' . $token];
    }
}

if (!function_exists('createAsignaturaPara')) {
    function createAsignaturaPara(Usuario $usuario, array $attrs = []): Asignatura
    {
        return Asignatura::create(array_merge([
            'id_usuario' => $usuario->id_usuario,
            'nombre' => 'Asignatura Test',
        ], $attrs));
    }
}

if (!function_exists('createTemaPara')) {
    function createTemaPara(Usuario $usuario, array $attrs = []): Tema
    {
        $asignatura = createAsignaturaPara($usuario);
        return Tema::create(array_merge([
            'id_asignatura' => $asignatura->id_asignatura,
            'nombre' => 'Tema Test',
            'orden' => 0,
        ], $attrs));
    }
}

if (!function_exists('createFullChain')) {
    function createFullChain(Usuario $usuario): array
    {
        $asignatura = Asignatura::create([
            'id_usuario' => $usuario->id_usuario,
            'nombre' => 'Asignatura Test',
        ]);

        $tema = Tema::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'nombre' => 'Tema Test',
            'orden' => 0,
        ]);

        return ['asignatura' => $asignatura, 'tema' => $tema];
    }
}
