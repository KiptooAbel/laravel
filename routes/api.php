<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MedicineController;
use App\Http\Controllers\Api\InventoryController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Test route
Route::get('/test', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API is working',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Protected routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // Medicine Management
    Route::prefix('medicines')->group(function () {
        Route::get('/', [MedicineController::class, 'index']);
        Route::post('/', [MedicineController::class, 'store']);
        Route::get('/search/barcode', [MedicineController::class, 'searchByBarcode']);
        Route::get('/{id}', [MedicineController::class, 'show']);
        Route::put('/{id}', [MedicineController::class, 'update']);
        Route::delete('/{id}', [MedicineController::class, 'destroy']);
        Route::get('/{id}/batches', [MedicineController::class, 'batches']);
    });
    
    // Inventory Management
    Route::prefix('inventory')->group(function () {
        Route::get('/movements', [InventoryController::class, 'movements']);
        Route::post('/adjust', [InventoryController::class, 'adjust']);
        Route::get('/low-stock', [InventoryController::class, 'lowStock']);
        Route::get('/expired', [InventoryController::class, 'expired']);
        Route::get('/expiring-soon', [InventoryController::class, 'expiringSoon']);
        Route::get('/valuation', [InventoryController::class, 'valuation']);
    });
});

// TODO: Add other routes as controllers are created
/*
    // POS & Sales - requires SalesController
    // Reports - requires ReportController
    // Suppliers - requires SupplierController
    // Purchases - requires PurchaseController
    // Settings - requires SettingsController
    // Sync - requires SyncController
*/
