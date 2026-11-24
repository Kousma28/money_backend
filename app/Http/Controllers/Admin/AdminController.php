<?php
// app/Http/Controllers/Admin/AdminController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Merchant;
use App\Models\Transfer;
use App\Models\MerchantTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Récupérer tous les utilisateurs
     */
    public function getUsers(Request $request): JsonResponse
    {
        $search = $request->get('search');
        $status = $request->get('status', 'all');
        $perPage = (int) $request->get('per_page', 20);

        $query = User::with(['country', 'wallet']);

        if ($status !== 'all') {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'suspended') {
                $query->where('is_active', false);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('phone', 'LIKE', "%{$search}%")
                    ->orWhere('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Suspendre un utilisateur
     */
    public function suspendUser(User $user): JsonResponse
    {
        $user->update(['is_active' => false]);
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur suspendu avec succès',
        ]);
    }

    /**
     * Activer un utilisateur
     */
    public function activateUser(User $user): JsonResponse
    {
        $user->update(['is_active' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur activé avec succès',
        ]);
    }

    /**
     * Relancer KYC
     */
    public function rerunKyc(User $user): JsonResponse
    {
        // Simuler un relancement KYC
        return response()->json([
            'success' => true,
            'message' => 'Vérification KYC relancée avec succès',
        ]);
    }

    /**
     * Exporter les utilisateurs
     */
    public function exportUsers(): JsonResponse
    {
        $users = User::with(['country', 'wallet'])->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="users_export_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($users) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'ID',
                'Téléphone',
                'Prénom',
                'Nom',
                'Pays',
                'Statut',
                'Vérifié',
                'Solde',
                'Date création',
            ]);

            foreach ($users as $user) {
                fputcsv($file, [
                    $user->id,
                    $user->phone,
                    $user->first_name ?? '',
                    $user->last_name ?? '',
                    $user->country?->name ?? 'N/A',
                    $user->is_active ? 'Actif' : 'Suspendu',
                    $user->phone_verified_at ? 'Oui' : 'Non',
                    $user->wallet?->balance ?? 0,
                    $user->created_at?->format('Y-m-d H:i:s') ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Envoyer une notification
     */
    public function sendNotification(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:500',
        ]);

        // Simuler l'envoi de notification
        \Illuminate\Support\Facades\Log::info('Notification envoyée', [
            'user_id' => $user->id,
            'message' => $request->message,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification envoyée avec succès',
        ]);
    }

    /**
     * Statistiques du dashboard
     */
    public function getStats(): JsonResponse
    {
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $suspendedUsers = User::where('is_active', false)->count();

        $totalTransfers = Transfer::count();
        $totalMerchantTx = MerchantTransaction::count();

        $totalVolume = Transfer::where('status', 'completed')->sum('amount_sent')
            + MerchantTransaction::where('status', 'completed')->sum('amount');

        $today = now()->toDateString();
        $todayTransfers = Transfer::whereDate('created_at', $today)->count();
        $todayMerchant = MerchantTransaction::whereDate('created_at', $today)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'users' => [
                    'total' => $totalUsers,
                    'active' => $activeUsers,
                    'suspended' => $suspendedUsers,
                ],
                'transactions' => [
                    'total' => $totalTransfers + $totalMerchantTx,
                    'today' => $todayTransfers + $todayMerchant,
                ],
                'volume' => (float) $totalVolume,
            ],
        ]);
    }
}