<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmPinRequest extends FormRequest
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
            'user_id' => 'required|integer|exists:users,id',
            'pin' => 'required|string|digits:4|regex:/^\d{4}$/',
            'pin_confirmation' => 'required|string|digits:4|same:pin',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'L\'identifiant utilisateur est requis.',
            'user_id.integer' => 'L\'identifiant utilisateur doit être un entier.',
            'user_id.exists' => 'L\'utilisateur n\'existe pas.',
            'pin.required' => 'Le code PIN est requis.',
            'pin.string' => 'Le code PIN doit être une chaîne de caractères.',
            'pin.digits' => 'Le code PIN doit contenir exactement 4 chiffres.',
            'pin.regex' => 'Le code PIN doit être composé uniquement de chiffres.',
            'pin_confirmation.required' => 'La confirmation du code PIN est requise.',
            'pin_confirmation.string' => 'La confirmation doit être une chaîne de caractères.',
            'pin_confirmation.digits' => 'La confirmation doit contenir exactement 4 chiffres.',
            'pin_confirmation.same' => 'La confirmation du code PIN ne correspond pas.',
        ];
    }
}
