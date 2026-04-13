<?php
// ── app/Models/Wallet.php ────────────────────────────────────────────────────
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
 
class Wallet extends Model
{
    protected $fillable = ['user_id', 'country_id', 'currency', 'balance', 'is_active'];
 
    protected $casts = ['balance' => 'decimal:4', 'is_active' => 'boolean'];
 
    public function user(): BelongsTo  { return $this->belongsTo(User::class); }
    public function country(): BelongsTo { return $this->belongsTo(Country::class); }
    public function transactions(): HasMany { return $this->hasMany(WalletTransaction::class); }
 
    /** Vérifie si le solde est suffisant pour le montant + frais. */
    public function hasSufficientBalance(float $amount): bool
    {
        return (float) $this->balance >= $amount;
    }
}
 