<?php
// app/Models/Wallet.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $fillable = [
        'user_id',
        'merchant_id',  // ✅ Ajouté
        'country_id',
        'currency',
        'balance',
        'is_active',
    ];

    protected $casts = [
        'balance' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    // ─── Relations ───────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function hasSufficientBalance(float $amount): bool
    {
        return (float) $this->balance >= $amount;
    }

    public function addBalance(float $amount, string $description = null): void
    {
        $this->balance = (float) $this->balance + $amount;
        $this->save();

        $this->transactions()->create([
            'type' => 'credit',
            'amount' => $amount,
            'currency' => $this->currency,
            'balance_before' => (float) $this->balance - $amount,
            'balance_after' => (float) $this->balance,
            'description' => $description ?? 'Crédit wallet',
        ]);
    }

    public function subtractBalance(float $amount, string $description = null): void
    {
        $this->balance = (float) $this->balance - $amount;
        $this->save();

        $this->transactions()->create([
            'type' => 'debit',
            'amount' => $amount,
            'currency' => $this->currency,
            'balance_before' => (float) $this->balance + $amount,
            'balance_after' => (float) $this->balance,
            'description' => $description ?? 'Débit wallet',
        ]);
    }
}