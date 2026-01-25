<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
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

// Protected routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // User Management (Owner only)
    Route::prefix('users')->middleware('permission:manage_users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/roles', [UserController::class, 'roles']);
        Route::get('/permissions', [UserController::class, 'permissions']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
        Route::post('/{id}/toggle-status', [UserController::class, 'toggleStatus']);
        Route::post('/{id}/assign-role', [UserController::class, 'assignRole']);
        Route::post('/{id}/assign-permissions', [UserController::class, 'assignPermissions']);
    });
    
    // Role Management (Owner only)
    Route::prefix('roles')->middleware('permission:manage_users')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::post('/', [RoleController::class, 'store']);
        Route::get('/permissions', [RoleController::class, 'permissions']);
        Route::get('/{id}', [RoleController::class, 'show']);
        Route::put('/{id}', [RoleController::class, 'update']);
        Route::delete('/{id}', [RoleController::class, 'destroy']);
    });
    
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
