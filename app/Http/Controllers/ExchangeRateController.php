<?phP

namespace App\Http\Controllers;
 
use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
 
class ExchangeRateController extends Controller
{
    /**
     * Liste tous les taux actifs.
     *
     * GET /api/exchange-rates
     */
    public function index(): JsonResponse
    {
        $rates = ExchangeRate::with(['fromCountry', 'toCountry'])
            ->where('is_active', true)
            ->get()
            ->map(fn ($r) => [
                'from_country_id'   => $r->from_country_id,
                'from_country_name' => $r->fromCountry?->name,
                'to_country_id'     => $r->to_country_id,
                'to_country_name'   => $r->toCountry?->name,
                'from_currency'     => $r->from_currency,
                'to_currency'       => $r->to_currency,
                'rate'              => (float) $r->rate,
                'fee_percent'       => (float) $r->fee_percent,
            ]);
 
        return response()->json(['success' => true, 'data' => $rates]);
    }
 
    /**
     * Taux pour un corridor précis.
     *
     * GET /api/exchange-rates/{fromCountryId}/{toCountryId}
     */
    public function show(int $fromCountryId, int $toCountryId): JsonResponse
    {
        $rate = ExchangeRate::with(['fromCountry', 'toCountry'])
            ->where('from_country_id', $fromCountryId)
            ->where('to_country_id', $toCountryId)
            ->where('is_active', true)
            ->first();
 
        if (! $rate) {
            return response()->json(['success' => false, 'message' => 'Corridor non disponible.'], 404);
        }
 
        return response()->json([
            'success' => true,
            'data'    => [
                'from_country_name' => $rate->fromCountry?->name,
                'to_country_name'   => $rate->toCountry?->name,
                'from_currency'     => $rate->from_currency,
                'to_currency'       => $rate->to_currency,
                'rate'              => (float) $rate->rate,
                'fee_percent'       => (float) $rate->fee_percent,
            ],
        ]);
    }
}
  
