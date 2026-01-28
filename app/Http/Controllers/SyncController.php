<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Medicine;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncController extends Controller
{
    /**
     * Push sales from client to server
     */
    public function pushSales(Request $request)
    {
        $request->validate([
            'sales' => 'required|array',
            'sales.*.local_id' => 'required|string',
            'sales.*.customer_name' => 'nullable|string|max:255',
            'sales.*.customer_phone' => 'nullable|string|max:20',
            'sales.*.discount' => 'nullable|numeric|min:0',
            'sales.*.vat_amount' => 'required|numeric|min:0',
            'sales.*.total_amount' => 'required|numeric|min:0',
            'sales.*.items' => 'required|array|min:1',
            'sales.*.items.*.medicine_id' => 'required|integer',
            'sales.*.items.*.quantity' => 'required|integer|min:1',
            'sales.*.items.*.unit_price' => 'required|numeric|min:0',
            'sales.*.items.*.subtotal' => 'required|numeric|min:0',
            'sales.*.payment' => 'required|array',
            'sales.*.payment.payment_method' => 'required|string|in:cash,card,mpesa',
            'sales.*.payment.amount_paid' => 'required|numeric|min:0',
            'sales.*.created_at' => 'required|date',
        ]);

        $results = [];
        $user = auth()->user();

        DB::beginTransaction();
        try {
            foreach ($request->sales as $saleData) {
                // Check if sale already synced
                $existingSale = Sale::where('local_id', $saleData['local_id'])->first();
                
                if ($existingSale) {
                    $results[] = [
                        'local_id' => $saleData['local_id'],
                        'server_id' => $existingSale->id,
                        'status' => 'already_synced',
                    ];
                    continue;
                }

                // Create sale with generated sale_number
                $sale = Sale::create([
                    'sale_number' => Sale::generateSaleNumber(),
                    'local_id' => $saleData['local_id'],
                    'user_id' => $user->id,
                    'customer_name' => $saleData['customer_name'] ?? null,
                    'customer_phone' => $saleData['customer_phone'] ?? null,
                    'subtotal' => $saleData['subtotal'] ?? $saleData['total_amount'],
                    'discount' => $saleData['discount'] ?? 0,
                    'vat_amount' => $saleData['vat_amount'] ?? 0,
                    'total' => $saleData['total_amount'],
                    'payment_method' => $saleData['payment']['payment_method'],
                    'created_at' => Carbon::parse($saleData['created_at']),
                ]);

                // Create sale items
                foreach ($saleData['items'] as $itemData) {
                    // Get medicine for name and get first available batch
                    $medicine = \App\Models\Medicine::find($itemData['medicine_id']);
                    $batch = $medicine ? $medicine->batches()->first() : null;
                    
                    $sale->items()->create([
                        'medicine_id' => $itemData['medicine_id'],
                        'batch_id' => $batch ? $batch->id : 1, // Use first batch or default to 1
                        'medicine_name' => $itemData['medicine_name'] ?? ($medicine ? $medicine->name : 'Unknown'),
                        'batch_number' => $batch ? $batch->batch_number : 'N/A',
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'discount' => $itemData['discount'] ?? 0,
                        'subtotal' => $itemData['subtotal'],
                        'vat_amount' => $itemData['vat_amount'] ?? 0,
                        'total' => $itemData['total'] ?? $itemData['subtotal'],
                    ]);
                }

                // Create payment
                $sale->payments()->create([
                    'payment_method' => $saleData['payment']['payment_method'],
                    'amount' => $saleData['payment']['amount_paid'],
                ]);

                $results[] = [
                    'local_id' => $saleData['local_id'],
                    'server_id' => $sale->id,
                    'status' => 'synced',
                ];
            }

            DB::commit();

            return response()->json([
                'message' => 'Sales synced successfully',
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Sync sales error: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to sync sales',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pull latest data from server to client
     */
    public function pullData(Request $request)
    {
        $request->validate([
            'last_sync' => 'nullable|date',
        ]);

        $lastSync = $request->last_sync 
            ? Carbon::parse($request->last_sync)
            : Carbon::now()->subYears(10);

        try {
            // Get medicines updated since last sync
            $medicines = Medicine::with(['batches' => function($query) use ($lastSync) {
                $query->where('updated_at', '>', $lastSync);
            }])
            ->where('updated_at', '>', $lastSync)
            ->get();

            // Get users updated since last sync
            $users = User::where('updated_at', '>', $lastSync)
                ->where('is_active', true)
                ->get(['id', 'name', 'email', 'is_active', 'updated_at']);

            // Get suppliers - for initial sync (no last_sync or very old), get ALL active suppliers
            // Otherwise, only get those updated since last sync
            $suppliersQuery = \App\Models\Supplier::query();
            if ($request->has('last_sync')) {
                // Regular sync - only updated suppliers
                $suppliersQuery->where('updated_at', '>', $lastSync);
            } else {
                // Initial sync - get all active suppliers
                $suppliersQuery->where('is_active', true);
            }
            $suppliers = $suppliersQuery->get(['id', 'name', 'contact_person', 'email', 'phone', 'address', 'is_active', 'updated_at']);

            // Get recent stock movements (limit to last 1000)
            $stockMovements = \App\Models\StockMovement::where('created_at', '>', $lastSync)
                ->orderBy('created_at', 'desc')
                ->limit(1000)
                ->get(['id', 'medicine_id', 'batch_id', 'type', 'quantity', 'notes', 'user_id', 'created_at']);

            return response()->json([
                'medicines' => $medicines,
                'users' => $users,
                'suppliers' => $suppliers,
                'stock_movements' => $stockMovements,
                'sync_timestamp' => Carbon::now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::error('Pull data error: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to pull data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get sync logs
     */
    public function getLogs(Request $request)
    {
        try {
            // Get recent sales with sync status
            $sales = Sale::with(['user:id,name', 'items.medicine:id,name'])
                ->orderBy('created_at', 'desc')
                ->take(100)
                ->get()
                ->map(function($sale) {
                    return [
                        'id' => $sale->id,
                        'local_id' => $sale->local_id,
                        'user' => $sale->user->name,
                        'total_amount' => $sale->total_amount,
                        'created_at' => $sale->created_at,
                        'is_synced' => $sale->local_id !== null,
                    ];
                });

            return response()->json([
                'logs' => $sales,
            ]);
        } catch (\Exception $e) {
            Log::error('Get logs error: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to get logs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check sync status
     */
    public function status()
    {
        try {
            $pendingSales = Sale::whereNull('local_id')->count();
            $lastSync = Sale::whereNotNull('local_id')
                ->orderBy('updated_at', 'desc')
                ->first();

            return response()->json([
                'pending_sales' => $pendingSales,
                'last_sync' => $lastSync ? $lastSync->updated_at : null,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::error('Sync status error: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to get sync status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
