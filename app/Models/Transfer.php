<?php
// app/Models/Transfer.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transfer extends Model
{
    protected $fillable = [
        'reference',
        'sender_id',
        'sender_phone',
        'from_country_id',
        'receiver_id',
        'receiver_phone',
        'to_country_id',
        'amount_sent',
        'currency_sent',
        'amount_received',
        'currency_received',
        'exchange_rate',
        'fee_amount',
        'fee_percent',
        'total_deducted',
        'status',
        'failure_reason',
        'channel',      // ✅ Ajouté
        'metadata',     // ✅ Ajouté
    ];

    protected $casts = [
        'amount_sent'     => 'decimal:4',
        'amount_received' => 'decimal:4',
        'exchange_rate'   => 'decimal:8',
        'fee_amount'      => 'decimal:4',
        'fee_percent'     => 'decimal:4',
        'total_deducted'  => 'decimal:4',
        'metadata'        => 'array',
    ];

    // ─── Relations ───────────────────────────────────────────────

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function fromCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'from_country_id');
    }

    public function toCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'to_country_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public static function generateReference(): string
    {
        return 'TXN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'Réussi',
            'pending' => 'En attente',
            'failed' => 'Échoué',
            'cancelled' => 'Annulé',
            default => $this->status,
        };
    }

    public function getChannelLabelAttribute(): string
    {
        return match ($this->channel) {
            'mobile' => 'Application Mobile',
            'web' => 'Web',
            'ussd' => 'USSD',
            'qr' => 'QR Code',
            'api' => 'API',
            default => $this->channel ?? 'N/A',
        };
    }
}