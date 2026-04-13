<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
 
class ExchangeRate extends Model
{
    protected $fillable = [
        'from_country_id', 'to_country_id',
        'from_currency', 'to_currency',
        'rate', 'fee_percent', 'is_active',
    ];
 
    protected $casts = ['rate' => 'decimal:8', 'fee_percent' => 'decimal:4', 'is_active' => 'boolean'];
 
    public function fromCountry(): BelongsTo { return $this->belongsTo(Country::class, 'from_country_id'); }
    public function toCountry(): BelongsTo   { return $this->belongsTo(Country::class, 'to_country_id'); }
 
    /** Calcule les frais pour un montant donné. */
    public function computeFee(float $amount): float
    {
        return round($amount * ((float) $this->fee_percent / 100), 4);
    }
 
    /** Convertit un montant source vers la devise cible. */
    public function convert(float $amount): float
    {
        return round($amount * (float) $this->rate, 4);
    }
}