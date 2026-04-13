<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Toutes les routes sont préfixées par /api (défini dans bootstrap/app.php
| ou RouteServiceProvider selon la version de Laravel).
|
*/

// ─── Routes publiques ────────────────────────────────────────────────────────

// Pays (liste pour le menu déroulant)
Route::prefix('countries')->group(function () {
    Route::get('/',        [CountryController::class, 'index']);
    Route::get('/search',  [CountryController::class, 'search']);
    Route::get('/{country}', [CountryController::class, 'show']);
});

// Inscription en 4 étapes
Route::prefix('register')->group(function () {
    // Étape 1 : numéro de téléphone + pays
    Route::post('/phone',       [RegistrationController::class, 'storePhone']);

    // Étape 2 : prénom + nom
    Route::post('/profile',     [RegistrationController::class, 'storeProfile']);

    // Étape 3 : création du code PIN (stocké temporairement)
    Route::post('/pin',         [RegistrationController::class, 'storePin']);

    // Étape 4 : confirmation du PIN → finalise le compte et retourne le token
    Route::post('/confirm-pin', [RegistrationController::class, 'confirmPin']);
});


  // Taux de change (public — utile pour l'affichage avant connexion)
  Route::get('/exchange-rates',                [ExchangeRateController::class, 'index']);
  Route::get('/exchange-rates/{from}/{to}',    [ExchangeRateController::class, 'show']);

// Connexion
Route::post('/login', [AuthController::class, 'login']);

// ─── Routes protégées (requièrent un Bearer token Sanctum) ───────────────────

Route::middleware('auth:sanctum')->group(function () {

    // Profil de l'utilisateur connecté
    Route::get('/me',      [AuthController::class, 'me']);

    // Déconnexion
    Route::post('/logout', [AuthController::class, 'logout']);

    // Envoi de message
    Route::post('/send-welcome', [AuthController::class, 'sendWelcomeMessage']);

    // Wallet
      Route::get('/wallet',                    [WalletController::class, 'show']);
      Route::post('/wallet/topup',             [WalletController::class, 'topup']);
      Route::get('/wallet/transactions',       [WalletController::class, 'transactions']);
 
      // Transferts
      Route::post('/transfers/simulate',       [TransferController::class, 'simulate']);
      Route::post('/transfers',                [TransferController::class, 'store']);
      Route::get('/transfers',                 [TransferController::class, 'index']);
  
 

    /*
    |----------------------------------------------------------------------
    | Ajoutez ici toutes les routes de votre application
    | qui nécessitent d'être authentifié.
    |----------------------------------------------------------------------
    |
    | Exemples :
    |
    | Route::get('/dashboard', [DashboardController::class, 'index']);
    | Route::put('/profile',   [ProfileController::class, 'update']);
    |
    */
});