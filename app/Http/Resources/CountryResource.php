<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CountryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'iso_code'  => $this->iso_code,
            'dial_code' => $this->dial_code,
            'flag'      => $this->flag,
            // Champ pratique pour afficher dans le dropdown : "🇸🇳 Sénégal (+221)"
            'label'     => "{$this->flag} {$this->name} ({$this->dial_code})",
        ];
    }
}