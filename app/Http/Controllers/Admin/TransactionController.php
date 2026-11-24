<?php
// app/Http/Controllers/Admin/TransactionController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transfer;
use App\Models\MerchantTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    /**
     * Récupérer toutes les transactions (admin)
     * GET /api/admin/transactions
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->get('type', 'all');
        $status = $request->get('status', 'all');
        $search = $request->get('search');
        $page = (int) $request->get('page', 1);
        $perPage = (int) $request->get('per_page', 20);

        $results = collect();

        // ─── Transfers ──────────────────────────────────────────
        $transfers = Transfer::with(['sender', 'receiver', 'fromCountry', 'toCountry'])
            ->when($type === 'transfer' || $type === 'all', function ($q) {
                return $q;
            })
            ->when($status !== 'all', function ($q) use ($status) {
                return $q->where('status', $status);
            })
            ->when($search, function ($q) use ($search) {
                return $q->where(function ($query) use ($search) {
                    $query->where('sender_phone', 'LIKE', "%{$search}%")
                        ->orWhere('receiver_phone', 'LIKE', "%{$search}%")
                        ->orWhere('reference', 'LIKE', "%{$search}%")
                        ->orWhereHas('sender', function ($q) use ($search) {
                            $q->where('first_name', 'LIKE', "%{$search}%")
                                ->orWhere('last_name', 'LIKE', "%{$search}%");
                        })
                        ->orWhereHas('receiver', function ($q) use ($search) {
                            $q->where('first_name', 'LIKE', "%{$search}%")
                                ->orWhere('last_name', 'LIKE', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->get();

        foreach ($transfers as $t) {
            $senderName = $t->sender ? trim($t->sender->first_name . ' ' . ($t->sender->last_name ?? '')) : 'N/A';
            $receiverName = $t->receiver ? trim($t->receiver->first_name . ' ' . ($t->receiver->last_name ?? '')) : 'N/A';
            $senderPhone = $t->sender_phone;
            $receiverPhone = $t->receiver_phone;

            $results->push([
                'id' => 'TXN-' . $t->id,
                'type' => 'transfer',
                'status' => $t->status,
                'amount' => (float) $t->amount_sent,
                'currency' => $t->currency_sent,
                'date' => $t->created_at ? $t->created_at->format('d M Y, H:i') : 'N/A',
                'channel' => $t->channel ?? 'mobile',
                'sender' => [
                    'name' => $senderName,
                    'phone' => $senderPhone,
                ],
                'receiver' => [
                    'name' => $receiverName,
                    'phone' => $receiverPhone,
                ],
                'merchant' => null,
                'from' => $senderName,
                'to' => $receiverName,
                'reference' => $t->reference,
                'fee' => (float) $t->fee_amount,
                'exchange_rate' => (float) $t->exchange_rate,
            ]);
        }

        // ─── Merchant Transactions ──────────────────────────────
        $merchantTransactions = MerchantTransaction::with(['user', 'merchant'])
            ->when(in_array($type, ['deposit', 'withdrawal', 'payment', 'all']), function ($q) use ($type) {
                if ($type === 'all') return $q;
                return $q->where('type', $type);
            })
            ->when($status !== 'all', function ($q) use ($status) {
                return $q->where('status', $status);
            })
            ->when($search, function ($q) use ($search) {
                return $q->where(function ($query) use ($search) {
                    $query->whereHas('user', function ($q) use ($search) {
                        $q->where('phone', 'LIKE', "%{$search}%")
                            ->orWhere('first_name', 'LIKE', "%{$search}%")
                            ->orWhere('last_name', 'LIKE', "%{$search}%");
                    })->orWhereHas('merchant', function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('merchant_id', 'LIKE', "%{$search}%")
                            ->orWhere('phone', 'LIKE', "%{$search}%");
                    });
                });
            })
            ->latest()
            ->get();

        foreach ($merchantTransactions as $t) {
            $userName = trim($t->user->first_name . ' ' . ($t->user->last_name ?? ''));
            $userPhone = $t->user->phone;

            $data = [
                'id' => 'MCH-' . $t->id,
                'type' => $t->type,
                'status' => $t->status,
                'amount' => (float) $t->amount,
                'currency' => $t->currency,
                'date' => $t->created_at ? $t->created_at->format('d M Y, H:i') : 'N/A',
                'channel' => $t->channel ?? 'mobile',
                'merchant' => [
                    'name' => $t->merchant->name,
                    'merchantId' => $t->merchant->merchant_id,
                    'phone' => $t->merchant->phone,
                ],
                'reference' => $t->reference,
                'description' => $t->description,
            ];

            if ($t->type === 'withdrawal') {
                $data['sender'] = null;
                $data['receiver'] = [
                    'name' => $userName,
                    'phone' => $userPhone,
                ];
                $data['from'] = $t->merchant->name;
                $data['to'] = $userName;
            } else {
                $data['sender'] = [
                    'name' => $userName,
                    'phone' => $userPhone,
                ];
                $data['receiver'] = null;
                $data['from'] = $userName;
                $data['to'] = $t->merchant->name;
            }

            $results->push($data);
        }

        // ─── Tri et pagination ──────────────────────────────────
        $sorted = $results->sortByDesc('date')->values();

        $total = $sorted->count();
        $items = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * Statistiques des transactions (admin)
     * GET /api/admin/transactions/stats
     */
    public function stats(): JsonResponse
    {
        $totalTransfers = Transfer::count();
        $totalMerchantTx = MerchantTransaction::count();

        $completedTransfers = Transfer::where('status', 'completed')->count();
        $pendingTransfers = Transfer::where('status', 'pending')->count();
        $failedTransfers = Transfer::where('status', 'failed')->count();

        $completedMerchant = MerchantTransaction::where('status', 'completed')->count();
        $pendingMerchant = MerchantTransaction::where('status', 'pending')->count();
        $failedMerchant = MerchantTransaction::where('status', 'failed')->count();

        $totalVolume = Transfer::where('status', 'completed')->sum('amount_sent')
            + MerchantTransaction::where('status', 'completed')->sum('amount');

        $today = now()->toDateString();
        $todayTransfers = Transfer::whereDate('created_at', $today)->count();
        $todayMerchant = MerchantTransaction::whereDate('created_at', $today)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_transactions' => $totalTransfers + $totalMerchantTx,
                'total_volume' => (float) $totalVolume,
                'successful' => $completedTransfers + $completedMerchant,
                'pending' => $pendingTransfers + $pendingMerchant,
                'failed' => $failedTransfers + $failedMerchant,
                'today' => $todayTransfers + $todayMerchant,
                'transfers' => [
                    'total' => $totalTransfers,
                    'completed' => $completedTransfers,
                    'pending' => $pendingTransfers,
                    'failed' => $failedTransfers,
                ],
                'merchant' => [
                    'total' => $totalMerchantTx,
                    'completed' => $completedMerchant,
                    'pending' => $pendingMerchant,
                    'failed' => $failedMerchant,
                ],
            ],
        ]);
    }

    /**
     * Détails d'une transaction (admin)
     * GET /api/admin/transactions/{id}
     */
    public function show(string $id): JsonResponse
    {
        if (str_starts_with($id, 'TXN-')) {
            $transfer = Transfer::with(['sender', 'receiver', 'fromCountry', 'toCountry'])
                ->find((int) str_replace('TXN-', '', $id));

            if (!$transfer) {
                return response()->json(['success' => false, 'message' => 'Transaction non trouvée'], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => 'TXN-' . $transfer->id,
                    'type' => 'transfer',
                    'status' => $transfer->status,
                    'amount' => (float) $transfer->amount_sent,
                    'currency' => $transfer->currency_sent,
                    'date' => $transfer->created_at ? $transfer->created_at->format('d M Y, H:i') : 'N/A',
                    'channel' => $transfer->channel ?? 'mobile',
                    'sender' => [
                        'name' => $transfer->sender ? trim($transfer->sender->first_name . ' ' . ($transfer->sender->last_name ?? '')) : 'N/A',
                        'phone' => $transfer->sender_phone,
                    ],
                    'receiver' => [
                        'name' => $transfer->receiver ? trim($transfer->receiver->first_name . ' ' . ($transfer->receiver->last_name ?? '')) : 'N/A',
                        'phone' => $transfer->receiver_phone,
                    ],
                    'reference' => $transfer->reference,
                    'fee' => (float) $transfer->fee_amount,
                    'exchange_rate' => (float) $transfer->exchange_rate,
                    'amount_received' => (float) $transfer->amount_received,
                    'currency_received' => $transfer->currency_received,
                    'from_country' => $transfer->fromCountry?->name,
                    'to_country' => $transfer->toCountry?->name,
                ],
            ]);
        }

        if (str_starts_with($id, 'MCH-')) {
            $transaction = MerchantTransaction::with(['user', 'merchant'])
                ->find((int) str_replace('MCH-', '', $id));

            if (!$transaction) {
                return response()->json(['success' => false, 'message' => 'Transaction non trouvée'], 404);
            }

            $userName = trim($transaction->user->first_name . ' ' . ($transaction->user->last_name ?? ''));

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => 'MCH-' . $transaction->id,
                    'type' => $transaction->type,
                    'status' => $transaction->status,
                    'amount' => (float) $transaction->amount,
                    'currency' => $transaction->currency,
                    'date' => $transaction->created_at ? $transaction->created_at->format('d M Y, H:i') : 'N/A',
                    'channel' => $transaction->channel ?? 'mobile',
                    'sender' => $transaction->type === 'withdrawal' ? null : [
                        'name' => $userName,
                        'phone' => $transaction->user->phone,
                    ],
                    'receiver' => $transaction->type === 'withdrawal' ? [
                        'name' => $userName,
                        'phone' => $transaction->user->phone,
                    ] : null,
                    'merchant' => [
                        'name' => $transaction->merchant->name,
                        'merchantId' => $transaction->merchant->merchant_id,
                        'phone' => $transaction->merchant->phone,
                    ],
                    'reference' => $transaction->reference,
                    'description' => $transaction->description,
                ],
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Transaction non trouvée'], 404);
    }
}