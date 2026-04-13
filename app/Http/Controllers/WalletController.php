<?php

namespace App\Http\Controllers;
 
use App\Http\Controllers\Controller;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
 
class WalletController extends Controller
{
    public function __construct(private readonly TransferService $transferService) {}
 
    /**
     * Solde du wallet de l'utilisateur connecté.
     *
     * GET /api/wallet
     * Auth: Bearer token
     */
    public function show(Request $request): JsonResponse
    {
        $wallet = $request->user()->wallet;
 
        if (! $wallet) {
            return response()->json([
                'success' => true,
                'data'    => ['balance' => 0, 'currency' => 'XAF'],
            ]);
        }
 
        return response()->json([
            'success' => true,
            'data'    => [
                'balance'    => (float) $wallet->balance,
                'currency'   => $wallet->currency,
                'is_active'  => $wallet->is_active,
                'updated_at' => $wallet->updated_at,
            ],
        ]);
    }
 
    /**
     * Recharger le wallet.
     * En production, cet endpoint est appelé par votre passerelle de paiement
     * (MTN MoMo, Orange Money, Stripe…) après confirmation du paiement.
     *
     * POST /api/wallet/topup
     * Body: { "amount": 10000, "description": "Recharge MTN" }
     * Auth: Bearer token
     */
    public function topup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount'      => 'required|numeric|min:100|max:10000000',
            'description' => 'nullable|string|max:255',
        ]);
 
        try {
            $transaction = $this->transferService->topup(
                user:        $request->user(),
                amount:      (float) $data['amount'],
                description: $data['description'] ?? 'Recharge',
            );
 
            $wallet = $request->user()->wallet()->first();
 
            return response()->json([
                'success' => true,
                'message' => 'Recharge effectuée avec succès.',
                'data'    => [
                    'transaction_id' => $transaction->id,
                    'amount'         => $transaction->amount,
                    'currency'       => $transaction->currency,
                    'balance_after'  => $transaction->balance_after,
                ],
            ]);
 
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation échouée.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
 
    /**
     * Historique des transactions du wallet.
     *
     * GET /api/wallet/transactions?page=1
     * Auth: Bearer token
     */
    public function transactions(Request $request): JsonResponse
    {
        $wallet = $request->user()->wallet;
 
        if (! $wallet) {
            return response()->json(['success' => true, 'data' => [], 'meta' => ['total' => 0]]);
        }
 
        $transactions = $wallet->transactions()
            ->latest()
            ->paginate(20);
 
        return response()->json([
            'success' => true,
            'data'    => $transactions->getCollection()->map(fn ($t) => [
                'id'             => $t->id,
                'type'           => $t->type,
                'amount'         => $t->amount,
                'currency'       => $t->currency,
                'balance_after'  => $t->balance_after,
                'description'    => $t->description,
                'created_at'     => $t->created_at,
            ]),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page'    => $transactions->lastPage(),
                'total'        => $transactions->total(),
            ],
        ]);
    }
}