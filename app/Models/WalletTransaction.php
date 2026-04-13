<?php
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
 
class WalletTransaction extends Model
{
    protected $fillable = [
        'wallet_id', 'transfer_id', 'type',
        'amount', 'currency', 'balance_before', 'balance_after', 'description',
    ];
 
    protected $casts = [
        'amount'         => 'decimal:4',
        'balance_before' => 'decimal:4',
        'balance_after'  => 'decimal:4',
    ];
 
    public function wallet(): BelongsTo   { return $this->belongsTo(Wallet::class); }
    public function transfer(): BelongsTo { return $this->belongsTo(Transfer::class); }
}