<?php

namespace App\Services;

use App\Models\Country;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RegistrationService
{
    /**
     * ÉTAPE 1 — Enregistre ou retrouve l'utilisateur avec son numéro de téléphone.
     * Retourne l'utilisateur (sans token, l'inscription n'est pas encore terminée).
     */
    public function storePhone(string $phone, int $countryId): User
    {
        $country = Country::findOrFail($countryId);

        // Crée l'utilisateur s'il n'existe pas encore (reprise possible)
        $user = User::firstOrCreate(
            ['phone' => $phone, 'country_id' => $countryId],
            ['country_code' => $country->dial_code]
        );

        return $user;
    }

    /**
     * ÉTAPE 2 — Enregistre le prénom et le nom.
     */
    public function storeProfile(int $userId, string $firstName, string $lastName): User
    {
        $user = User::findOrFail($userId);

        $user->update([
            'first_name' => $firstName,
            'last_name'  => $lastName,
        ]);

        return $user->fresh();
    }

    /**
     * ÉTAPE 3 — Enregistre temporairement le PIN (en clair dans la session,
     * il sera haché uniquement après confirmation à l'étape 4).
     * Retourne le PIN en clair pour que l'étape 4 puisse le comparer.
     *
     * NOTE : si vous ne souhaitez pas passer le PIN côté client entre les deux
     * étapes, stockez-le en cache Redis avec une clé liée à l'userId.
     */
    public function storePin(int $userId, string $pin): bool
    {
        // Vérifier que l'utilisateur existe et a bien rempli le profil
        $user = User::findOrFail($userId);

        if (blank($user->first_name) || blank($user->last_name)) {
            throw ValidationException::withMessages([
                'profile' => 'Veuillez compléter votre profil avant de définir un code PIN.',
            ]);
        }

        // On stocke temporairement en cache (60 secondes, le temps de confirmer)
        cache()->put("registration_pin_{$userId}", $pin, now()->addMinutes(10));

        return true;
    }

    /**
     * ÉTAPE 4 — Confirme le PIN, le hache et finalise la création du compte.
     * Retourne le token Sanctum.
     */
    public function confirmPin(int $userId, string $pin, string $pinConfirmation): array
    {
        if ($pin !== $pinConfirmation) {
            throw ValidationException::withMessages([
                'pin_confirmation' => 'Les deux codes PIN ne correspondent pas.',
            ]);
        }

        // Récupère le PIN temporaire stocké à l'étape 3
        $tempPin = cache()->get("registration_pin_{$userId}");

        if ($tempPin === null) {
            throw ValidationException::withMessages([
                'pin' => 'La session a expiré. Veuillez recommencer l\'étape précédente.',
            ]);
        }

        if ($tempPin !== $pin) {
            throw ValidationException::withMessages([
                'pin' => 'Le code PIN ne correspond pas à celui saisi précédemment.',
            ]);
        }

        $user = User::findOrFail($userId);

        $user->update([
            'pin'                => Hash::make($pin),
            'phone_verified_at'  => now(),
        ]);

        // Nettoyage du cache temporaire
        cache()->forget("registration_pin_{$userId}");

        // Génération du token Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user'  => $user->fresh(),
            'token' => $token,
        ];
    }
}