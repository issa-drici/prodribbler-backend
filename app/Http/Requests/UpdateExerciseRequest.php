<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExerciseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Vous pouvez ajouter une logique d'autorisation ici si nécessaire
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'duration_seconds' => 'sometimes|integer|min:1',
            'xp_value' => 'sometimes|integer|min:0',
            'level_id' => 'sometimes|nullable|uuid|exists:levels,id',
            'banner_url' => 'sometimes|nullable|string|url',
            'video_url' => 'sometimes|nullable|string|url',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'level_id.uuid' => 'Le level_id doit être un UUID valide.',
            'level_id.exists' => 'Le niveau spécifié n\'existe pas.',
            'duration_seconds.min' => 'La durée doit être supérieure à 0.',
            'xp_value.min' => 'La valeur XP doit être supérieure ou égale à 0.',
            'banner_url.url' => 'L\'URL de la bannière doit être une URL valide.',
            'video_url.url' => 'L\'URL de la vidéo doit être une URL valide.',
        ];
    }
}

