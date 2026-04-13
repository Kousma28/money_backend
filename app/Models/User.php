<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'phone',
        'country_id',
        'country_code',
        'first_name',
        'last_name',
        'pin',
        'phone_verified_at',
        'is_active',
    ];

    protected $hidden = [
        'pin',
        'remember_token',
    ];

    protected $casts = [
        'phone_verified_at' => 'datetime',
        'is_active'         => 'boolean',
    ];

    // ─── Relations ───────────────────────────────────────────────

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    /**
     * Retourne le numéro complet avec indicatif.
     * Ex: +221 77 123 45 67
     */
    public function fullPhone(): string
    {
        return $this->country_code . ' ' . $this->phone;
    }

    /**
     * Vérifie si l'inscription est complète (toutes les étapes passées).
     */
    public function isRegistrationComplete(): bool
    {
        return filled($this->first_name)
            && filled($this->last_name)
            && filled($this->pin);
    }
    
    public function wallet(): HasOne
    {
        return $this->hasOne(\App\Models\Wallet::class);
    }
 
    public function transfersSent(): HasMany
    {
        return $this->hasMany(\App\Models\Transfer::class, 'sender_id');
    }
 
    public function transfersReceived(): HasMany
    {
        return $this->hasMany(\App\Models\Transfer::class, 'receiver_id');
    }
}