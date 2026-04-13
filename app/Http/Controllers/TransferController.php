<?php

// ═══════════════════════════════════════════════════════════════════════════
// app/Http/Controllers/TransferController.php
// ═══════════════════════════════════════════════════════════════════════════
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TransferController extends Controller
{
    public function __construct(private readonly TransferService $transferService) {}

    /**
     * Aperçu avant envoi — aucune écriture en base.
     *
     * POST /api/transfers/simulate
     * Body: { "to_country_id": 2, "amount": 50000 }
     * Auth: Bearer token
     */
    public function simulate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to_country_id' => 'required|integer|exists:countries,id',
            'amount'        => 'required|numeric|min:1|max:5000000',
        ]);

        try {
            $preview = $this->transferService->simulate(
                fromCountryId: $request->user()->country_id,
                toCountryId:   (int) $data['to_country_id'],
                amount:        (float) $data['amount'],
            );

            return response()->json(['success' => true, 'data' => $preview]);

        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation échouée.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Envoyer de l'argent.
     *
     * POST /api/transfers
     * Body: { "receiver_phone": "771234567", "to_country_id": 2, "amount": 50000 }
     * Auth: Bearer token
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'receiver_phone' => 'required|string|max:20',
            'to_country_id'  => 'required|integer|exists:countries,id',
            'amount'         => 'required|numeric|min:100|max:5000000',
        ]);

        try {
            $transfer = $this->transferService->send(
                sender:        $request->user(),
                receiverPhone: $data['receiver_phone'],
                toCountryId:   (int) $data['to_country_id'],
                amount:        (float) $data['amount'],
            );

            return response()->json([
                'success' => true,
                'message' => 'Transfert effectué avec succès.',
                'data'    => [
                    'reference'         => $transfer->reference,
                    'amount_sent'       => $transfer->amount_sent,
                    'currency_sent'     => $transfer->currency_sent,
                    'amount_received'   => $transfer->amount_received,
                    'currency_received' => $transfer->currency_received,
                    'fee_amount'        => $transfer->fee_amount,
                    'total_deducted'    => $transfer->total_deducted,
                    'exchange_rate'     => $transfer->exchange_rate,
                    'status'            => $transfer->status,
                    'receiver_phone'    => $transfer->receiver_phone,
                    'to_country'        => $transfer->toCountry?->name,
                    'created_at'        => $transfer->created_at,
                ],
            ], 201);

        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation échouée.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Historique des transferts de l'utilisateur connecté.
     *
     * GET /api/transfers?page=1&per_page=15
     * Auth: Bearer token
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 50);
        $filter  = $request->get('filter', 'all');

        $query = \App\Models\Transfer::query()
            ->where(function ($query) use ($request) {
                $query->where('sender_id', $request->user()->id)
                      ->orWhere('receiver_id', $request->user()->id);
            })
            ->with(['toCountry', 'fromCountry', 'sender', 'receiver'])
            ->latest();

        if ($filter === 'sent') {
            $query->where('sender_id', $request->user()->id);
        } elseif ($filter === 'received') {
            $query->where('receiver_id', $request->user()->id);
        }

        $transfers = $query->paginate($perPage);

        $items = $transfers->getCollection()->map(fn ($t) => [
            'id'               => $t->id,
            'reference'        => $t->reference,
            'type'             => $t->sender_id === $request->user()->id ? 'sent' : 'received',
            'name'             => $t->sender_id === $request->user()->id
                ? ($t->receiver?->first_name ? trim($t->receiver->first_name . ' ' . ($t->receiver->last_name ?? '')) : $t->receiver_phone)
                : ($t->sender?->first_name ? trim($t->sender->first_name . ' ' . ($t->sender->last_name ?? '')) : $t->sender_phone),
            'phone'            => $t->sender_id === $request->user()->id ? $t->receiver_phone : $t->sender_phone,
            'country'          => $t->sender_id === $request->user()->id ? $t->toCountry?->name : $t->fromCountry?->name,
            'flag'             => $t->sender_id === $request->user()->id ? $t->toCountry?->flag : $t->fromCountry?->flag,
            'amount'           => $t->sender_id === $request->user()->id ? $t->amount_sent : $t->amount_received,
            'currency'         => $t->sender_id === $request->user()->id ? $t->currency_sent : $t->currency_received,
            'status'           => $t->status,
            'created_at'       => $t->created_at,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $items,
            'meta'    => [
                'current_page' => $transfers->currentPage(),
                'last_page'    => $transfers->lastPage(),
                'total'        => $transfers->total(),
                'filter'       => $filter,
            ],
        ]);
    }
}