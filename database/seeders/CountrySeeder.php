<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            [
                'name' => 'République du Congo',
                'iso_code' => 'CG',
                'dial_code' => '+242',
                'flag' => '🇨🇬'
            ],
            [
                'name' => 'République Démocratique du Congo',
                'iso_code' => 'CD',
                'dial_code' => '+243',
                'flag' => '🇨🇩'
            ],
            [
                'name' => 'Sénégal',
                'iso_code' => 'SN',
                'dial_code' => '+221',
                'flag' => '🇸🇳'
            ],
        ];

        // Ajout des timestamps
        $now = now();
        $countries = array_map(fn($c) => array_merge($c, [
            'created_at' => $now,
            'updated_at' => $now,
        ]), $countries);

        // Tri alphabétique
        usort($countries, fn($a, $b) => strcmp($a['name'], $b['name']));

        DB::table('countries')->upsert(
            $countries,
            ['iso_code'],
            ['name', 'dial_code', 'flag', 'updated_at']
        );
    }
}