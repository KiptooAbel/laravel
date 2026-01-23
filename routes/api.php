<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// Public routes
Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login']);
Route::post('/register', [App\Http\Controllers\Api\AuthController::class, 'register']);

// Protected routes
Route::middleware(['auth:sanctum'])->group(function () {
    
    // Auth
    Route::post('/logout', [App\Http\Controllers\Api\AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user()->load('roles', 'permissions');
    });

    // Medicine Management
    Route::prefix('medicines')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\MedicineController::class, 'index'])
            ->middleware('permission:view_medicines');
        Route::post('/', [App\Http\Controllers\Api\MedicineController::class, 'store'])
            ->middleware('permission:create_medicine');
        Route::get('/{id}', [App\Http\Controllers\Api\MedicineController::class, 'show'])
            ->middleware('permission:view_medicines');
        Route::put('/{id}', [App\Http\Controllers\Api\MedicineController::class, 'update'])
            ->middleware('permission:edit_medicine');
        Route::delete('/{id}', [App\Http\Controllers\Api\MedicineController::class, 'destroy'])
            ->middleware('permission:delete_medicine');
        Route::get('/{id}/batches', [App\Http\Controllers\Api\MedicineController::class, 'batches'])
            ->middleware('permission:view_medicines');
    });

    // Inventory Management
    Route::prefix('inventory')->group(function () {
        Route::get('/stock-movements', [App\Http\Controllers\Api\InventoryController::class, 'movements'])
            ->middleware('permission:view_stock_movements');
        Route::post('/adjust', [App\Http\Controllers\Api\InventoryController::class, 'adjust'])
            ->middleware('permission:adjust_stock');
        Route::get('/low-stock', [App\Http\Controllers\Api\InventoryController::class, 'lowStock'])
            ->middleware('permission:view_medicines');
        Route::get('/expired', [App\Http\Controllers\Api\InventoryController::class, 'expired'])
            ->middleware('permission:view_medicines');
        Route::get('/expiring-soon', [App\Http\Controllers\Api\InventoryController::class, 'expiringSoon'])
            ->middleware('permission:view_medicines');
    });

    // POS & Sales
    Route::prefix('sales')->group(function () {
        Route::post('/', [App\Http\Controllers\Api\SalesController::class, 'create'])
            ->middleware('permission:sell_medicine');
        Route::get('/', [App\Http\Controllers\Api\SalesController::class, 'index'])
            ->middleware('permission:view_sales');
        Route::get('/{id}', [App\Http\Controllers\Api\SalesController::class, 'show'])
            ->middleware('permission:view_sales');
        Route::post('/{id}/void', [App\Http\Controllers\Api\SalesController::class, 'void'])
            ->middleware('permission:void_sale');
    });

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('/daily-sales', [App\Http\Controllers\Api\ReportController::class, 'dailySales'])
            ->middleware('permission:view_reports');
        Route::get('/profit', [App\Http\Controllers\Api\ReportController::class, 'profit'])
            ->middleware('permission:view_profit_reports');
        Route::get('/stock-valuation', [App\Http\Controllers\Api\ReportController::class, 'stockValuation'])
            ->middleware('permission:view_reports');
        Route::get('/expired-items', [App\Http\Controllers\Api\ReportController::class, 'expiredItems'])
            ->middleware('permission:view_reports');
    });

    // Suppliers
    Route::prefix('suppliers')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\SupplierController::class, 'index'])
            ->middleware('permission:view_suppliers');
        Route::post('/', [App\Http\Controllers\Api\SupplierController::class, 'store'])
            ->middleware('permission:manage_suppliers');
        Route::get('/{id}', [App\Http\Controllers\Api\SupplierController::class, 'show'])
            ->middleware('permission:view_suppliers');
        Route::put('/{id}', [App\Http\Controllers\Api\SupplierController::class, 'update'])
            ->middleware('permission:manage_suppliers');
        Route::delete('/{id}', [App\Http\Controllers\Api\SupplierController::class, 'destroy'])
            ->middleware('permission:manage_suppliers');
    });

    // Purchases
    Route::prefix('purchases')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\PurchaseController::class, 'index'])
            ->middleware('permission:view_purchases');
        Route::post('/', [App\Http\Controllers\Api\PurchaseController::class, 'store'])
            ->middleware('permission:create_purchase');
        Route::get('/{id}', [App\Http\Controllers\Api\PurchaseController::class, 'show'])
            ->middleware('permission:view_purchases');
    });

    // Settings
    Route::prefix('settings')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\SettingsController::class, 'index'])
            ->middleware('permission:manage_settings');
        Route::put('/', [App\Http\Controllers\Api\SettingsController::class, 'update'])
            ->middleware('permission:manage_settings');
    });

    // Sync (for offline mode)
    Route::prefix('sync')->group(function () {
        Route::post('/sales', [App\Http\Controllers\Api\SyncController::class, 'syncSales']);
        Route::get('/data', [App\Http\Controllers\Api\SyncController::class, 'pullData']);
    });
});
