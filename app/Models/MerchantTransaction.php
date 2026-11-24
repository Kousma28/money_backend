<?php
// app/Models/MerchantTransaction.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'merchant_id',
        'type',
        'amount',
        'currency',
        'status',
        'channel',
        'reference',
        'description',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'metadata' => 'array',
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

    // ─── Helpers ─────────────────────────────────────────────────

    public static function generateReference(): string
    {
        return 'MCH-TXN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'deposit' => 'Dépôt',
            'withdrawal' => 'Retrait',
            'payment' => 'Paiement Marchand',
            default => $this->type,
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'Réussi',
            'pending' => 'En attente',
            'failed' => 'Échoué',
            default => $this->status,
        };
    }
}