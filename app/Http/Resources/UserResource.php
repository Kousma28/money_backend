<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\CountryResource;

class UserResource extends JsonResource
{
    /**
     * Le PIN est toujours exclu de la réponse.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'phone'        => $this->phone,
            'country_code' => $this->country_code,
            'full_phone'   => $this->country_code . $this->phone,
            'first_name'   => $this->first_name,
            'last_name'    => $this->last_name,
            'full_name'    => trim("{$this->first_name} {$this->last_name}"),
            'is_active'    => $this->is_active,
            'is_complete'  => $this->isRegistrationComplete(),
            'country'      => $this->whenLoaded('country', fn() => new CountryResource($this->country)),
            'verified_at'  => $this->phone_verified_at?->toIso8601String(),
            'created_at'   => $this->created_at->toIso8601String(),
        ];
    }
}