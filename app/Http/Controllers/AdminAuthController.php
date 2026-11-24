<?php
// app/Http/Controllers/AdminAuthController.php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminAuthController extends Controller
{
    /**
     * Inscription d'un nouvel admin
     *
     * POST /api/auth/register
     * Body: { name, email, password, password_confirmation }
     */
    public function register(Request $request): JsonResponse
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Créer l'admin
            $admin = Admin::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'admin',
                'is_active' => true,
            ]);

            // Créer le token
            $token = $admin->createToken('admin_auth_token', ['admin'])->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Compte créé avec succès.',
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'user' => [
                        'id' => $admin->id,
                        'email' => $admin->email,
                        'name' => $admin->name,
                        'role' => $admin->role,
                    ],
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création du compte.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Connexion admin
     *
     * POST /api/auth/login
     * Body: { email, password }
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        // Chercher l'admin par email
        $admin = Admin::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email ou mot de passe incorrect.',
            ], 401);
        }

        // Vérifier si le compte admin est actif
        if (!$admin->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte administrateur a été désactivé.',
            ], 403);
        }

        // Révoquer les anciens tokens et créer un nouveau
        $admin->tokens()->delete();
        $token = $admin->createToken('admin_auth_token', ['admin'])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $admin->id,
                    'email' => $admin->email,
                    'name' => $admin->name,
                    'role' => $admin->role ?? 'admin',
                ],
            ],
        ]);
    }

    /**
     * Déconnexion admin
     *
     * POST /api/auth/logout
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
     * Profil de l'admin connecté
     *
     * GET /api/auth/me
     * Header: Authorization: Bearer {token}
     */
    public function me(Request $request): JsonResponse
    {
        $admin = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $admin->id,
                'email' => $admin->email,
                'name' => $admin->name,
                'role' => $admin->role ?? 'admin',
            ],
        ]);
    }

    /**
     * Valider le token
     *
     * GET /api/auth/validate
     * Header: Authorization: Bearer {token}
     */
    public function validateToken(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'valid' => true,
        ]);
    }
}