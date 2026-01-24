<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Protected routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
});

// TODO: Add other routes as controllers are created
/*
    // Medicine Management - requires MedicineController
    // Inventory Management - requires InventoryController
    // POS & Sales - requires SalesController
    // Reports - requires ReportController
    // Suppliers - requires SupplierController
    // Purchases - requires PurchaseController
    // Settings - requires SettingsController
    // Sync - requires SyncController
*/
