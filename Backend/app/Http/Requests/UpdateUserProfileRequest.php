<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_completo' => 'required|string|max:100',
            'email' => [
                'required',
                'email',
                'max:100',
                Rule::unique('usuarios', 'email')->ignore($this->user()->id_usuario, 'id_usuario'),
            ],
        ];
    }
}
