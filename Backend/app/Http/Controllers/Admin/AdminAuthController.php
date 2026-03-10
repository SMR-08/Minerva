<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    /**
     * Mostrar formulario de login.
     */
    public function showLoginForm()
    {
        // Si ya está autenticado y es admin, redirigir al dashboard
        if (Auth::check() && Auth::user()->id_rol === 1) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    /**
     * Procesar login.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ], [
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El formato del email no es válido.',
            'password.required' => 'La contraseña es obligatoria.',
        ]);

        $credentials = [
            'email' => $request->email,
            'password' => $request->password,
        ];

        $remember = $request->filled('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            // Verificar que sea admin
            if (Auth::user()->id_rol !== 1) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw ValidationException::withMessages([
                    'email' => 'No tienes permisos de administrador.',
                ]);
            }

            // Actualizar último acceso
            Auth::user()->update(['ultimo_acceso' => now()]);

            // Redirigir a la página intentada o al dashboard
            return redirect()->intended(route('admin.dashboard'));
        }

        throw ValidationException::withMessages([
            'email' => 'Las credenciales proporcionadas son incorrectas.',
        ]);
    }

    /**
     * Cerrar sesión.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('status', 'Sesión cerrada correctamente.');
    }
}
