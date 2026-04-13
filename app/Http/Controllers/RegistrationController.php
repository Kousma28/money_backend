<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmPinRequest;
use App\Http\Requests\PhoneRequest;
use App\Http\Requests\PinRequest;
use App\Http\Requests\ProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class RegistrationController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registrationService
    ) {}

    /**
     * ÉTAPE 1 — Téléphone + pays
     *
     * POST /api/register/phone
     * Body: { "phone": "771234567", "country_id": 120 }
     */
    public function storePhone(PhoneRequest $request): JsonResponse
    {
        try {
            $user = $this->registrationService->storePhone(
                phone:     $request->validated('phone'),
                countryId: $request->validated('country_id'),
            );

            return response()->json([
                'success' => true,
                'message' => 'Numéro de téléphone enregistré.',
                'data'    => [
                    'user_id'      => $user->id,
                    'phone'        => $user->phone,
                    'country_code' => $user->country_code,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ÉTAPE 2 — Prénom + nom
     *
     * POST /api/register/profile
     * Body: { "user_id": 1, "first_name": "Mamadou", "last_name": "Diallo" }
     */
    public function storeProfile(ProfileRequest $request): JsonResponse
    {
        $userId = $request->input('user_id');

        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Identifiant utilisateur manquant.',
            ], 422);
        }

        try {
            $user = $this->registrationService->storeProfile(
                userId:    (int) $userId,
                firstName: $request->validated('first_name'),
                lastName:  $request->validated('last_name'),
            );

            return response()->json([
                'success' => true,
                'message' => 'Profil enregistré.',
                'data'    => new UserResource($user),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ÉTAPE 3 — Création du code PIN
     *
     * POST /api/register/pin
     * Body: { "user_id": 1, "pin": "1234" }
     */
    public function storePin(PinRequest $request): JsonResponse
    {
        $userId = $request->input('user_id');

        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Identifiant utilisateur manquant.',
            ], 422);
        }

        try {
            $this->registrationService->storePin(
                userId: (int) $userId,
                pin:    $request->validated('pin'),
            );

            return response()->json([
                'success' => true,
                'message' => 'Code PIN enregistré temporairement. Veuillez le confirmer.',
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ÉTAPE 4 — Confirmation du code PIN et création finale du compte
     *
     * POST /api/register/confirm-pin
     * Body: { "user_id": 1, "pin": "1234", "pin_confirmation": "1234" }
     */
    public function confirmPin(ConfirmPinRequest $request): JsonResponse
    {
        $userId = $request->input('user_id');

        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Identifiant utilisateur manquant.',
            ], 422);
        }

        try {
            $result = $this->registrationService->confirmPin(
                userId:          (int) $userId,
                pin:             $request->validated('pin'),
                pinConfirmation: $request->validated('pin_confirmation'),
            );

            return response()->json([
                'success' => true,
                'message' => 'Compte créé avec succès. Bienvenue !',
                'data'    => [
                    'user'         => new UserResource($result['user']),
                    'token'        => $result['token'],
                    'token_type'   => 'Bearer',
                ],
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}