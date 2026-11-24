<?php
// app/Http/Controllers/Admin/MerchantController.php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantController extends Controller
{
    /**
     * Liste des marchands
     *
     * GET /api/admin/merchants
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->get('search');
        $status = $request->get('status', 'all');
        $perPage = (int) $request->get('per_page', 20);

        $query = Merchant::withCount('transactions')
            ->withSum('transactions', 'amount');

        if ($status !== 'all') {
            if ($status === 'active' || $status === 'approved') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive' || $status === 'suspended') {
                $query->where('is_active', false);
            } elseif ($status === 'pending') {
                $query->where('is_active', false);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('merchant_id', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        $merchants = $query->latest()->paginate($perPage);

        // Enrichir les données
        $merchants->getCollection()->transform(function ($m) {
            return [
                'id' => $m->id,
                'merchant_id' => $m->merchant_id,
                'name' => $m->name,
                'phone' => $m->phone,
                'email' => $m->email,
                'country_id' => $m->country_id,
                'address' => $m->address,
                'category' => $m->category,
                'logo' => $m->logo,
                'is_active' => $m->is_active,
                'status' => $m->is_active ? 'approved' : 'pending',
                'volume' => (float) ($m->transactions_sum_amount ?? 0),
                'tx' => $m->transactions_count ?? 0,
                'rating' => 4.5,
                'created_at' => $m->created_at,
                'updated_at' => $m->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $merchants,
        ]);
    }

    /**
     * Afficher un marchand
     *
     * GET /api/admin/merchants/{merchant}
     */
    public function show(Merchant $merchant): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $merchant->id,
                'merchant_id' => $merchant->merchant_id,
                'name' => $merchant->name,
                'phone' => $merchant->phone,
                'email' => $merchant->email,
                'country_id' => $merchant->country_id,
                'address' => $merchant->address,
                'category' => $merchant->category,
                'logo' => $merchant->logo,
                'is_active' => $merchant->is_active,
                'status' => $merchant->is_active ? 'approved' : 'pending',
                'created_at' => $merchant->created_at,
                'updated_at' => $merchant->updated_at,
            ],
        ]);
    }

    /**
     * Suspendre un marchand
     *
     * POST /api/admin/merchants/{merchant}/suspend
     */
    public function suspend(Merchant $merchant): JsonResponse
    {
        $merchant->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Marchand suspendu avec succès',
        ]);
    }

    /**
     * Activer un marchand
     *
     * POST /api/admin/merchants/{merchant}/activate
     */
    public function activate(Merchant $merchant): JsonResponse
    {
        $merchant->update(['is_active' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Marchand activé avec succès',
        ]);
    }

    /**
     * Exporter les marchands
     *
     * GET /api/admin/merchants/export
     */
    public function export(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $merchants = Merchant::withCount('transactions')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="merchants_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($merchants) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'ID Marchand',
                'Nom',
                'Téléphone',
                'Email',
                'Catégorie',
                'Adresse',
                'Statut',
                'Transactions',
                'Date création',
            ]);

            foreach ($merchants as $m) {
                fputcsv($file, [
                    $m->merchant_id,
                    $m->name,
                    $m->phone,
                    $m->email ?? '',
                    $m->category ?? '',
                    $m->address ?? '',
                    $m->is_active ? 'Actif' : 'Suspendu',
                    $m->transactions_count ?? 0,
                    $m->created_at?->format('Y-m-d H:i:s') ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}