<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\ExchangeRate;
use Illuminate\Database\Seeder;

/**
 * Remplit la table exchange_rates pour les corridors actifs.
 *
 * Taux provisoires (à mettre à jour via l'admin ou une API externe) :
 *   1 XAF (Congo-Brazzaville) = 1.000 XOF (Sénégal)   ← zone franc, parité fixe
 *   1 XOF (Sénégal)           = 1.000 XAF (Congo-Brazzaville)
 *   1 XAF (Congo-Brazzaville) = 0.0016 USD (Congo-Kinshasa)  ← approximatif
 *   1 USD (Congo-Kinshasa)    = 625.0  XAF (Congo-Brazzaville)
 */
class ExchangeRateSeeder extends Seeder
{
    public function run(): void
    {
        // Récupère les pays par iso_code
        $cg = Country::where('iso_code', 'CG')->firstOrFail(); // Congo-Brazzaville XAF
        $sn = Country::where('iso_code', 'SN')->firstOrFail(); // Sénégal           XOF
        $cd = Country::where('iso_code', 'CD')->firstOrFail(); // Congo-Kinshasa    CDF/USD

        $corridors = [
            // Congo-Brazzaville → Sénégal  (zone franc, parité 1:1)
            [
                'from_country_id' => $cg->id,
                'to_country_id'   => $sn->id,
                'from_currency'   => 'XAF',
                'to_currency'     => 'XOF',
                'rate'            => 1.00000000,
                'fee_percent'     => 2.0,
                'is_active'       => true,
            ],
            // Sénégal → Congo-Brazzaville  (parité inverse)
            [
                'from_country_id' => $sn->id,
                'to_country_id'   => $cg->id,
                'from_currency'   => 'XOF',
                'to_currency'     => 'XAF',
                'rate'            => 1.00000000,
                'fee_percent'     => 2.0,
                'is_active'       => true,
            ],
            // Congo-Brazzaville → Congo-Kinshasa
            [
                'from_country_id' => $cg->id,
                'to_country_id'   => $cd->id,
                'from_currency'   => 'XAF',
                'to_currency'     => 'USD',
                'rate'            => 0.00160000,  // 1 XAF ≈ 0.0016 USD
                'fee_percent'     => 2.0,
                'is_active'       => true,
            ],
            // Congo-Kinshasa → Congo-Brazzaville
            [
                'from_country_id' => $cd->id,
                'to_country_id'   => $cg->id,
                'from_currency'   => 'USD',
                'to_currency'     => 'XAF',
                'rate'            => 625.00000000, // 1 USD ≈ 625 XAF
                'fee_percent'     => 2.0,
                'is_active'       => true,
            ],
            // Sénégal → Congo-Kinshasa
            [
                'from_country_id' => $sn->id,
                'to_country_id'   => $cd->id,
                'from_currency'   => 'XOF',
                'to_currency'     => 'USD',
                'rate'            => 0.00160000,
                'fee_percent'     => 2.0,
                'is_active'       => true,
            ],
            // Congo-Kinshasa → Sénégal
            [
                'from_country_id' => $cd->id,
                'to_country_id'   => $sn->id,
                'from_currency'   => 'USD',
                'to_currency'     => 'XOF',
                'rate'            => 625.00000000,
                'fee_percent'     => 2.0,
                'is_active'       => true,
            ],
        ];

        foreach ($corridors as $corridor) {
            ExchangeRate::updateOrCreate(
                [
                    'from_country_id' => $corridor['from_country_id'],
                    'to_country_id'   => $corridor['to_country_id'],
                ],
                $corridor
            );
        }
    }
}