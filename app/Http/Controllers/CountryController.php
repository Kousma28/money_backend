<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\CountryResource;
use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    /**
     * Retourne la liste de tous les pays triés par nom.
     * Utilisé pour remplir le menu déroulant de sélection du pays.
     *
     * GET /api/countries
     */
    public function index(): JsonResponse
    {
        $countries = Country::orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data'    => CountryResource::collection($countries),
        ]);
    }

    /**
     * Retourne un pays spécifique par son ID.
     *
     * GET /api/countries/{id}
     */
    public function show(Country $country): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new CountryResource($country),
        ]);
    }

    /**
     * Recherche de pays par nom ou indicatif (pour une éventuelle barre de recherche).
     *
     * GET /api/countries/search?q=sen
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');

        if (strlen($query) < 1) {
            return response()->json([
                'success' => true,
                'data'    => [],
            ]);
        }

        $countries = Country::where('name', 'LIKE', "%{$query}%")
                            ->orWhere('dial_code', 'LIKE', "%{$query}%")
                            ->orWhere('iso_code', 'LIKE', "%{$query}%")
                            ->orderBy('name')
                            ->limit(20)
                            ->get();

        return response()->json([
            'success' => true,
            'data'    => CountryResource::collection($countries),
        ]);
    }
}