<?php

namespace App\Services;

use App\Models\ExchangeRate;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferService
{
    // ─────────────────────────────────────────────────────────────────────────
    // RECHARGE DU WALLET (topup)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Crédite le wallet de l'utilisateur.
     * Dans un vrai projet, cet appel serait déclenché par un callback
     * de passerelle de paiement (MTN, Orange Money…).
     *
     * @param  User   $user
     * @param  float  $amount   Montant en devise locale
     * @param  string $description
     * @return WalletTransaction
     */
    public function topup(User $user, float $amount, string $description = 'Recharge'): WalletTransaction
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Le montant doit être positif.']);
        }

        return DB::transaction(function () use ($user, $amount, $description) {
            $wallet = $this->getOrCreateWallet($user);

            $balanceBefore    = (float) $wallet->balance;
            $wallet->balance  = $balanceBefore + $amount;
            $wallet->save();

            return WalletTransaction::create([
                'wallet_id'      => $wallet->id,
                'transfer_id'    => null,
                'type'           => 'topup',
                'amount'         => $amount,
                'currency'       => $wallet->currency,
                'balance_before' => $balanceBefore,
                'balance_after'  => (float) $wallet->balance,
                'description'    => $description,
            ]);
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SIMULATION (aperçu avant envoi)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calcule les frais et le montant converti sans écrire en base.
     * Utilisé par le front pour afficher le récapitulatif.
     *
     * @return array{
     *   amount_sent: float,  currency_sent: string,
     *   fee_amount: float,   fee_percent: float,
     *   total_deducted: float,
     *   amount_received: float, currency_received: string,
     *   exchange_rate: float
     * }
     */
    public function simulate(int $fromCountryId, int $toCountryId, float $amount): array
    {
        $rate = $this->getActiveRate($fromCountryId, $toCountryId);

        $fee           = $rate->computeFee($amount);
        $totalDeducted = $amount + $fee;
        $amountReceived = $rate->convert($amount); // les frais restent côté expéditeur

        return [
            'amount_sent'       => $amount,
            'currency_sent'     => $rate->from_currency,
            'fee_amount'        => $fee,
            'fee_percent'       => (float) $rate->fee_percent,
            'total_deducted'    => $totalDeducted,
            'amount_received'   => $amountReceived,
            'currency_received' => $rate->to_currency,
            'exchange_rate'     => (float) $rate->rate,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ENVOI D'ARGENT
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Effectue un transfert international entre deux pays.
     *
     * @param  User   $sender         Utilisateur connecté (expéditeur)
     * @param  string $receiverPhone  Numéro du destinataire (avec indicatif)
     * @param  int    $toCountryId    ID du pays destinataire
     * @param  float  $amount         Montant à envoyer (en devise expéditeur)
     * @return Transfer
     *
     * @throws ValidationException  Si le solde est insuffisant ou le taux inexistant
     */
    public function send(User $sender, string $receiverPhone, int $toCountryId, float $amount): Transfer
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Le montant doit être positif.']);
        }

        $fromCountryId = $sender->country_id;

        // Les envois dans le même pays ne sont pas autorisés ici
        if ($fromCountryId === $toCountryId) {
            throw ValidationException::withMessages([
                'to_country_id' => 'Les transferts dans le même pays ne sont pas supportés.',
            ]);
        }

        $rate = $this->getActiveRate($fromCountryId, $toCountryId);

        $fee           = $rate->computeFee($amount);
        $totalDeducted = $amount + $fee;
        $amountReceived = $rate->convert($amount);

        return DB::transaction(function () use (
            $sender, $receiverPhone, $toCountryId, $amount,
            $rate, $fee, $totalDeducted, $amountReceived
        ) {
            // 1. Wallet expéditeur
            $senderWallet = $this->getOrCreateWallet($sender);

            if (! $senderWallet->hasSufficientBalance($totalDeducted)) {
                throw ValidationException::withMessages([
                    'amount' => "Solde insuffisant. Solde disponible : {$senderWallet->balance} {$senderWallet->currency}.",
                ]);
            }

            // 2. Destinataire (peut ne pas être inscrit → null)
            $receiver = User::where('phone', $receiverPhone)
                            ->where('country_id', $toCountryId)
                            ->first();

            // 3. Créer le transfert
            $transfer = Transfer::create([
                'reference'         => Transfer::generateReference(),
                'sender_id'         => $sender->id,
                'sender_phone'      => $sender->phone,
                'from_country_id'   => $sender->country_id,
                'receiver_id'       => $receiver?->id,
                'receiver_phone'    => $receiverPhone,
                'to_country_id'     => $toCountryId,
                'amount_sent'       => $amount,
                'currency_sent'     => $rate->from_currency,
                'amount_received'   => $amountReceived,
                'currency_received' => $rate->to_currency,
                'exchange_rate'     => $rate->rate,
                'fee_amount'        => $fee,
                'fee_percent'       => $rate->fee_percent,
                'total_deducted'    => $totalDeducted,
                'status'            => 'pending',
            ]);

            // 4. Débiter l'expéditeur
            $this->debit($senderWallet, $totalDeducted, $transfer, 'Envoi vers ' . $receiverPhone);

            // 5. Créditer le destinataire (s'il a un compte)
            if ($receiver) {
                $receiverWallet = $this->getOrCreateWallet($receiver, $rate->to_currency, $toCountryId);
                $this->credit($receiverWallet, $amountReceived, $transfer, 'Reçu de ' . $sender->phone);
            }

            // 6. Marquer le transfert comme terminé
            $transfer->update(['status' => 'completed']);

            return $transfer->fresh(['fromCountry', 'toCountry', 'sender', 'receiver']);
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS PRIVÉS
    // ─────────────────────────────────────────────────────────────────────────

    private function getActiveRate(int $fromCountryId, int $toCountryId): ExchangeRate
    {
        $rate = ExchangeRate::where('from_country_id', $fromCountryId)
                            ->where('to_country_id', $toCountryId)
                            ->where('is_active', true)
                            ->first();

        if (! $rate) {
            throw ValidationException::withMessages([
                'to_country_id' => 'Ce corridor de transfert n\'est pas encore disponible.',
            ]);
        }

        return $rate;
    }

    private function getOrCreateWallet(User $user, ?string $currency = null, ?int $countryId = null): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id],
            [
                'country_id' => $countryId ?? $user->country_id,
                'currency'   => $currency  ?? $this->defaultCurrency($user->country_id),
                'balance'    => 0,
                'is_active'  => true,
            ]
        );
    }

    private function debit(Wallet $wallet, float $amount, Transfer $transfer, string $description): WalletTransaction
    {
        $balanceBefore   = (float) $wallet->balance;
        $wallet->balance = $balanceBefore - $amount;
        $wallet->save();

        return WalletTransaction::create([
            'wallet_id'      => $wallet->id,
            'transfer_id'    => $transfer->id,
            'type'           => 'debit',
            'amount'         => $amount,
            'currency'       => $wallet->currency,
            'balance_before' => $balanceBefore,
            'balance_after'  => (float) $wallet->balance,
            'description'    => $description,
        ]);
    }

    private function credit(Wallet $wallet, float $amount, Transfer $transfer, string $description): WalletTransaction
    {
        $balanceBefore   = (float) $wallet->balance;
        $wallet->balance = $balanceBefore + $amount;
        $wallet->save();

        return WalletTransaction::create([
            'wallet_id'      => $wallet->id,
            'transfer_id'    => $transfer->id,
            'type'           => 'credit',
            'amount'         => $amount,
            'currency'       => $wallet->currency,
            'balance_before' => $balanceBefore,
            'balance_after'  => (float) $wallet->balance,
            'description'    => $description,
        ]);
    }

    /** Devise par défaut selon le pays (à compléter selon votre données). */
    private function defaultCurrency(int $countryId): string
    {
        // Vous pouvez remplacer ça par une lecture de la table countries
        // si vous y ajoutez une colonne `currency`.
        $map = [
            // Congo-Brazzaville → XAF
            // Sénégal            → XOF
            // Congo-Kinshasa     → USD
        ];
        return $map[$countryId] ?? 'XAF';
    }
}