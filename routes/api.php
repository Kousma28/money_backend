
<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\MerchantController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - PAYLIO
|--------------------------------------------------------------------------
*/

// ============================================================
// ROUTES PUBLIQUES
// ============================================================

// Pays
Route::prefix('countries')->group(function () {
    Route::get('/', [CountryController::class, 'index']);
    Route::get('/search', [CountryController::class, 'search']);
    Route::get('/{country}', [CountryController::class, 'show']);
});

// Inscription client
Route::prefix('register')->group(function () {
    Route::post('/phone', [RegistrationController::class, 'storePhone']);
    Route::post('/profile', [RegistrationController::class, 'storeProfile']);
    Route::post('/pin', [RegistrationController::class, 'storePin']);
    Route::post('/confirm-pin', [RegistrationController::class, 'confirmPin']);
});

// Connexion client
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

// ============================================================
// AUTHENTIFICATION ADMINISTRATEUR
// ============================================================

Route::prefix('auth')->group(function () {

    // Connexion administrateur
    // L'inscription publique est volontairement supprimée.
    Route::post('/login', [AdminAuthController::class, 'login'])
        ->middleware('throttle:5,1');

    // Routes réservées aux administrateurs actifs
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/me', [AdminAuthController::class, 'me']);
        Route::get('/validate', [AdminAuthController::class, 'validateToken']);
    });
});

// ============================================================
// ROUTES CLIENTS AUTHENTIFIÉS
// ============================================================

Route::middleware(['auth:sanctum', 'client'])->group(function () {

    // Transferts
    Route::prefix('transfers')->group(function () {
        Route::post('/simulate', [TransferController::class, 'simulate']);
        Route::post('/', [TransferController::class, 'store']);
        Route::get('/', [TransferController::class, 'index']);
    });

    // Portefeuille
    Route::prefix('wallet')->group(function () {
        Route::get('/', [WalletController::class, 'show']);
        Route::post('/topup', [WalletController::class, 'topup']);
        Route::get('/transactions', [WalletController::class, 'transactions']);
    });

    // Profil et déconnexion client
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Message de bienvenue
    Route::post('/send-welcome', [AuthController::class, 'sendWelcomeMessage']);
});

// ============================================================
// ROUTES ADMINISTRATEUR
// ============================================================

Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin')
    ->group(function () {

        // Dashboard
        Route::get('/stats', [AdminController::class, 'getStats']);

        // Gestion des utilisateurs
        Route::prefix('users')->group(function () {
            Route::get('/', [AdminController::class, 'getUsers']);
            Route::get('/export', [AdminController::class, 'exportUsers']);

            Route::post('/{user}/suspend', [AdminController::class, 'suspendUser']);
            Route::post('/{user}/activate', [AdminController::class, 'activateUser']);
            Route::post('/{user}/rerun-kyc', [AdminController::class, 'rerunKyc']);
            Route::post('/{user}/notify', [AdminController::class, 'sendNotification']);
        });

        // Gestion des transactions
        Route::prefix('transactions')->group(function () {
            Route::get('/', [TransactionController::class, 'index']);
            Route::get('/stats', [TransactionController::class, 'stats']);
            Route::get('/{id}', [TransactionController::class, 'show']);
        });

        // Gestion des marchands
        Route::prefix('merchants')->group(function () {
            Route::get('/', [MerchantController::class, 'index']);
            Route::get('/export', [MerchantController::class, 'export']);

            Route::post('/{merchant}/suspend', [MerchantController::class, 'suspend']);
            Route::post('/{merchant}/activate', [MerchantController::class, 'activate']);
        });
    });
