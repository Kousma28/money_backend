<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Vérifier que l'utilisateur est bien un administrateur
        if (!$user instanceof Admin) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé. Zone administrateur.',
            ], 403);
        }

        // Vérifier que le compte administrateur est actif
        if (!$user->is_active) {
            // Révoquer le token utilisé
            $token = $user->currentAccessToken();

            if ($token && method_exists($token, 'delete')) {
                $token->delete();
            }

            return response()->json([
                'success' => false,
                'message' => 'Votre compte administrateur est désactivé.',
            ], 403);
        }

        return $next($request);
    }
}
