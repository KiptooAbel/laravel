# Laravel Backend Structure

## Directory Organization

```
laravel/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       ├── AuthController.php (Login, Register, Logout)
│   │   │       ├── MedicineController.php (CRUD, Batches)
│   │   │       ├── InventoryController.php (Stock movements, Adjustments)
│   │   │       ├── SalesController.php (POS operations, Sales history)
│   │   │       ├── ReportController.php (Reports & Analytics)
│   │   │       ├── SupplierController.php (Supplier management)
│   │   │       ├── PurchaseController.php (Purchase orders)
│   │   │       ├── SettingsController.php (App settings)
│   │   │       └── SyncController.php (Offline sync)
│   │   │
│   │   ├── Middleware/
│   │   │   └── CheckPermission.php
│   │   │
│   │   └── Requests/
│   │       ├── LoginRequest.php
│   │       ├── StoreMedicineRequest.php
│   │       ├── CreateSaleRequest.php
│   │       └── ...
│   │
│   ├── Models/
│   │   ├── User.php (with HasRoles, HasApiTokens)
│   │   ├── Medicine.php
│   │   ├── MedicineBatch.php
│   │   ├── StockMovement.php
│   │   ├── Sale.php
│   │   ├── SaleItem.php
│   │   ├── Payment.php
│   │   ├── Supplier.php
│   │   ├── Purchase.php
│   │   ├── PurchaseItem.php
│   │   ├── PharmacyProfile.php
│   │   └── Setting.php
│   │
│   └── Services/
│       ├── SalesService.php (FIFO logic, Stock deduction)
│       ├── InventoryService.php (Stock calculations)
│       └── ReportService.php (Report generation)
│
├── database/
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 0001_01_01_000001_create_cache_table.php
│   │   ├── 0001_01_01_000002_create_jobs_table.php
│   │   ├── 2026_01_23_222107_create_permission_tables.php
│   │   ├── 2026_01_24_000001_create_personal_access_tokens_table.php
│   │   ├── XXXX_XX_XX_create_pharmacy_profiles_table.php (TODO)
│   │   ├── XXXX_XX_XX_create_medicines_table.php (TODO)
│   │   ├── XXXX_XX_XX_create_medicine_batches_table.php (TODO)
│   │   ├── XXXX_XX_XX_create_stock_movements_table.php (TODO)
│   │   ├── XXXX_XX_XX_create_sales_table.php (TODO)
│   │   ├── XXXX_XX_XX_create_sale_items_table.php (TODO)
│   │   ├── XXXX_XX_XX_create_payments_table.php (TODO)
│   │   ├── XXXX_XX_XX_create_suppliers_table.php (TODO)
│   │   ├── XXXX_XX_XX_create_purchases_table.php (TODO)
│   │   ├── XXXX_XX_XX_create_purchase_items_table.php (TODO)
│   │   └── XXXX_XX_XX_create_settings_table.php (TODO)
│   │
│   └── seeders/
│       ├── DatabaseSeeder.php
│       └── RolesAndPermissionsSeeder.php (✓ Created)
│
├── routes/
│   ├── api.php (✓ Created with all endpoints)
│   └── web.php
│
└── config/
    ├── sanctum.php (✓ Published)
    └── permission.php (✓ Published)
```

## API Authentication Flow

1. User logs in via `/api/login`
2. Sanctum generates a token
3. Token stored in Flutter (SharedPreferences)
4. All subsequent requests include: `Authorization: Bearer {token}`
5. Middleware validates token and permissions

## Permission-Based Routes

All API routes are protected with:
- `auth:sanctum` middleware
- `permission:{permission_name}` middleware

Example:
```php
Route::post('/medicines', [MedicineController::class, 'store'])
    ->middleware(['auth:sanctum', 'permission:create_medicine']);
```

## Next Steps

1. Run migrations: `php artisan migrate`
2. Seed roles & permissions: `php artisan db:seed`
3. Create remaining migrations for business models
4. Implement controllers
5. Test API endpoints with Postman/Insomnia
