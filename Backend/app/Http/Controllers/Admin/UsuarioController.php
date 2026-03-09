<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Usuario;
use App\Models\Rol;
use App\Models\EstadoUsuario;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{
    public function indexView()
    {
        $usuarios = Usuario::with(['rol', 'estado'])->paginate(15);
        $roles = Rol::all();
        $estados = EstadoUsuario::all();
        return view('admin.users', compact('usuarios', 'roles', 'estados'));
    }

    /**
     * Crear un nuevo usuario (ADMIN o USUARIO).
     */
    public function store(Request $request)
    {
        $reglas = [
            'nombre_completo' => 'required|string|max:100',
            'email' => 'required|email|unique:usuarios,email',
            'password' => 'required|string|min:6',
            'id_rol' => 'required|exists:roles,id_rol',
            'id_estado' => 'required|exists:estados_usuario,id_estado',
        ];

        $mensajes = [
            'required' => 'Es obligatorio rellenar todos los campos (Nombre, Email, Contraseña, etc).',
            'email.unique' => 'Ya existe un usuario con este correo electrónico.',
            'email.email' => 'El formato del correo parece incorrecto.',
            'password.min' => 'La contraseña debe tener un mínimo de 6 caracteres.',
            'nombre_completo.max' => 'El nombre no puede superar los 100 caracteres.',
            'id_rol.exists' => 'El rol seleccionado no es válido en el sistema.',
            'id_estado.exists' => 'El estado seleccionado no es válido en el sistema.'
        ];

        $request->validate($reglas, $mensajes);

        $usuario = Usuario::create([
            'nombre_completo' => $request->nombre_completo,
            'email' => $request->email,
            'password_hash' => Hash::make($request->password),
            'id_rol' => $request->id_rol,
            'id_estado' => $request->id_estado,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Usuario creado correctamente', 'usuario' => $usuario], 201);
        }

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuario creado correctamente.');
    }

    /**
     * Actualizar usuario.
     */
    public function update(Request $request, string $id)
    {
        $usuario = Usuario::findOrFail($id);

        $reglas = [
            'nombre_completo' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|unique:usuarios,email,' . $id . ',id_usuario',
            'password' => 'sometimes|nullable|string|min:6',
            'id_rol' => 'sometimes|exists:roles,id_rol',
            'id_estado' => 'sometimes|exists:estados_usuario,id_estado',
        ];

        $mensajes = [
            'email.unique' => 'Ese correo electrónico ya lo está usando otro usuario.',
            'email.email' => 'El formato del correo parece incorrecto.',
            'password.min' => 'Si introduces una nueva contraseña, debe tener al menos 6 caracteres.',
            'nombre_completo.max' => 'El nombre no puede superar los 100 caracteres.',
            'id_rol.exists' => 'El rol seleccionado no es válido.',
            'id_estado.exists' => 'El estado seleccionado no es válido.'
        ];

        $request->validate($reglas, $mensajes);

        $datos = $request->except(['password', '_token', '_method']);
        if ($request->filled('password')) {
            $datos['password_hash'] = Hash::make($request->password);
        }

        $usuario->update($datos);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Usuario actualizado', 'usuario' => $usuario]);
        }

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuario actualizado correctamente.');
    }

    /**
     * Eliminar usuario.
     */
    public function destroy(Request $request, string $id)
    {
        $usuario = Usuario::findOrFail($id);
        $usuario->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Usuario eliminado correctamente']);
        }

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuario eliminado correctamente.');
    }
}
