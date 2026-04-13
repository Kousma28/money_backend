<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Http\Requests\SendWelcomeMessageRequest;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function __construct(private readonly MessageService $messageService) {}
    /**
     * Connexion par numéro de téléphone + code PIN.
     *
     * POST /api/login
     * Body: { "phone": "771234567", "country_id": 120, "pin": "1234" }
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // Cherche l'utilisateur par téléphone ET pays
        $user = User::where('phone', $request->validated('phone'))
                    ->where('country_id', $request->validated('country_id'))
                    ->first();

        // Vérification utilisateur + PIN
        if (! $user || ! Hash::check($request->validated('pin'), $user->pin)) {
            return response()->json([
                'success' => false,
                'message' => 'Numéro de téléphone ou code PIN incorrect.',
            ], 401);
        }

        // Compte désactivé
        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte a été désactivé. Contactez le support.',
            ], 403);
        }

        // Inscription incomplète
        if (! $user->isRegistrationComplete()) {
            return response()->json([
                'success' => false,
                'message' => 'Votre inscription n\'est pas encore terminée.',
            ], 403);
        }

        // Révoque les anciens tokens et crée un nouveau
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        try {
            $this->messageService->sendWelcomeSms(
                $user,
                $this->buildWelcomeMessage($user)
            );
        } catch (\Throwable $e) {
            Log::error('Impossible d\'envoyer le SMS de bienvenue après connexion.', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success'    => true,
            'message'    => 'Connexion réussie.',
            'data'       => [
                'user'       => new UserResource($user),
                'token'      => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Envoie un message de bienvenue à l'utilisateur connecté.
     *
     * POST /api/send-welcome
     * Header: Authorization: Bearer {token}
     * Body: { "message": "...", "type": "welcome" }
     */
    public function sendWelcomeMessage(SendWelcomeMessageRequest $request): JsonResponse
    {
        $user = $request->user();
        $message = $request->validated('message');

        $this->messageService->sendWelcomeSms($user, $message);

        return response()->json([
            'success' => true,
            'message' => 'Message de bienvenue envoyé.',
        ]);
    }

    /**
     * Déconnexion — révoque le token courant.
     *
     * POST /api/logout
     * Header: Authorization: Bearer {token}
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie.',
        ]);
    }

    /**
     * Retourne le profil de l'utilisateur connecté.
     *
     * GET /api/me
     * Header: Authorization: Bearer {token}
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new UserResource($request->user()),
        ]);
    }

    private function buildWelcomeMessage(User $user): string
    {
        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

        return ($name ? "Bonjour {$name}, " : 'Bienvenue, ') .
            'Merci d\'avoir choisi Paylio pour vos transferts d\'argent. Nous sommes ravis de vous compter parmi nos utilisateurs.';
    }
}