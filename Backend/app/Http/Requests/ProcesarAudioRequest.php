<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcesarAudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'audio' => 'required|file|mimes:mp3,wav,ogg,m4a,flac,webm,aac,wma,opus|max:' . (config('audio.max_size_mb', 2048) * 1024),
            'idioma' => 'nullable|string|in:es,en,ca,eu,gl,fr,de,pt,it,auto',
            'titulo' => 'nullable|string|max:200',
            'activar_asignacion_roles' => 'nullable|boolean',
        ];
    }
}
