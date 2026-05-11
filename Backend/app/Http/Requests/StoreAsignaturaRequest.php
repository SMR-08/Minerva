<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAsignaturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:100',
            'profesor' => 'nullable|string|max:100',
            'color_hex' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icono' => 'nullable|string|max:50',
            'descripcion' => 'nullable|string|max:500',
            'semestre' => 'nullable|string|max:50',
        ];
    }
}
