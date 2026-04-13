<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PhoneRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'phone' => 'required|string|min:1|max:20',
            'country_id' => 'required|integer|exists:countries,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'phone.required' => 'Le numéro de téléphone est requis.',
            'phone.string' => 'Le numéro de téléphone doit être une chaîne de caractères.',
            'phone.min' => 'Le numéro de téléphone doit contenir au moins 1 caractère.',
            'phone.max' => 'Le numéro de téléphone ne peut pas dépasser 20 caractères.',
            'country_id.required' => 'Le pays est requis.',
            'country_id.integer' => 'L\'ID du pays doit être un entier.',
            'country_id.exists' => 'Le pays sélectionné n\'existe pas.',
        ];
    }
}
