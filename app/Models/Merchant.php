<?php
// app/Models/Merchant.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Merchant extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'name',
        'phone',
        'email',
        'country_id',
        'address',
        'category',
        'logo',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ─── Relations ───────────────────────────────────────────────

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(MerchantTransaction::class);
    }

    public function wallet(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public static function generateMerchantId(): string
    {
        return 'MCH-' . str_pad((string) rand(10000, 99999), 5, '0', STR_PAD_LEFT);
    }

    public function fullPhone(): string
    {
        return $this->phone ? $this->phone : 'N/A';
    }
}