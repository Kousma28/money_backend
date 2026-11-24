<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class ClientMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Vérifier qu'il s'agit bien d'un client
        if (!$user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux clients.',
            ], 403);
        }

        // Vérifier que le compte est actif
        if (!$user->is_active) {
            $token = $user->currentAccessToken();

            if ($token && method_exists($token, 'delete')) {
                $token->delete();
            }

            return response()->json([
                'success' => false,
                'message' => 'Votre compte est désactivé.',
            ], 403);
        }

        return $next($request);
    }
}
