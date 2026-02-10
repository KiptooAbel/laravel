<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /**
     * Get stock movements with filters
     */
    public function movements(Request $request): JsonResponse
    {
        $query = StockMovement::with(['medicine:id,name', 'user:id,name', 'batch:id,batch_number']);

        // Filter by medicine
        if ($request->has('medicine_id')) {
            $query->where('medicine_id', $request->medicine_id);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $movements = $query->latest()->paginate($request->get('per_page', 20));

        return response()->json($movements);
    }

    /**
     * Adjust stock for a medicine (add or remove)
     */
    public function adjust(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'medicine_id' => 'required|exists:medicines,id',
            'batch_id' => 'nullable|exists:medicine_batches,id',
            'quantity' => 'required|integer|not_in:0',
            'type' => 'required|in:purchase,sale,adjustment,damage,expiry,return',
            'notes' => 'nullable|string',
            'batch_data' => 'required_without:batch_id|array', // For creating new batch
            'batch_data.batch_number' => 'required_with:batch_data|string',
            'batch_data.expiry_date' => 'required_with:batch_data|date|after:today',
            'batch_data.cost_price_per_unit' => 'required_with:batch_data|numeric|min:0',
            'batch_data.selling_price_per_unit' => 'required_with:batch_data|numeric|min:0',
            'batch_data.manufacture_date' => 'nullable|date',
            'batch_data.supplier_id' => 'nullable|exists:suppliers,id',
        ]);

        DB::beginTransaction();
        try {
            $medicine = Medicine::findOrFail($validated['medicine_id']);

            // If adding stock and no batch_id, create new batch
            if ($validated['quantity'] > 0 && !isset($validated['batch_id'])) {
                $batch = MedicineBatch::create([
                    'medicine_id' => $medicine->id,
                    'batch_number' => $validated['batch_data']['batch_number'],
                    'quantity' => $validated['quantity'],
                    'initial_quantity' => $validated['quantity'],
                    'cost_price_per_unit' => $validated['batch_data']['cost_price_per_unit'],
                    'selling_price_per_unit' => $validated['batch_data']['selling_price_per_unit'],
                    'manufacture_date' => $validated['batch_data']['manufacture_date'] ?? null,
                    'expiry_date' => $validated['batch_data']['expiry_date'],
                    'received_date' => now(),
                    'supplier_id' => $validated['batch_data']['supplier_id'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                ]);
                $batchId = $batch->id;
            } else {
                // Adjusting existing batch
                $batch = MedicineBatch::findOrFail($validated['batch_id']);
                $batch->quantity += $validated['quantity'];
                
                if ($batch->quantity < 0) {
                    throw new \Exception('Insufficient stock in batch');
                }
                
                $batch->save();
                $batchId = $batch->id;
            }

            // Record stock movement
            $movement = StockMovement::create([
                'medicine_id' => $medicine->id,
                'batch_id' => $batchId,
                'type' => $validated['type'],
                'quantity' => $validated['quantity'],
                'balance_after' => $medicine->fresh()->total_stock,
                'unit_price' => $batch->cost_price_per_unit,
                'notes' => $validated['notes'] ?? null,
                'user_id' => auth()->id(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Stock adjusted successfully',
                'movement' => $movement->load(['medicine', 'batch', 'user']),
                'new_stock' => $medicine->fresh()->total_stock
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Stock adjustment failed: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get medicines with low stock
     */
    public function lowStock(): JsonResponse
    {
        $medicines = Medicine::whereRaw('(SELECT SUM(quantity) FROM medicine_batches 
            WHERE medicine_batches.medicine_id = medicines.id 
            AND medicine_batches.quantity > 0 
            AND medicine_batches.expiry_date > NOW()) <= medicines.reorder_level')
            ->with(['activeBatches'])
            ->get()
            ->map(function($medicine) {
                return [
                    'medicine' => $medicine,
                    'current_stock' => $medicine->total_stock,
                    'reorder_level' => $medicine->reorder_level,
                    'shortage' => max(0, $medicine->reorder_level - $medicine->total_stock),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'medicines' => $medicines,
                'total' => $medicines->count(),
            ],
        ]);
    }

    /**
     * Get inventory alerts (low stock, expired, expiring soon)
     */
    public function alerts(Request $request): JsonResponse
    {
        $alertType = $request->get('alert_type');
        $perPage = $request->get('per_page', 15);
        
        // Default to low_stock if no type specified
        if ($alertType === 'low_stock' || !$alertType) {
            $query = Medicine::whereRaw('(SELECT SUM(quantity) FROM medicine_batches 
                WHERE medicine_batches.medicine_id = medicines.id 
                AND medicine_batches.quantity > 0 
                AND (medicine_batches.expiry_date IS NULL OR medicine_batches.expiry_date > NOW())) <= medicines.reorder_level')
                ->with(['activeBatches']);
            
            $result = $query->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        }
        
        // For expired items
        if ($alertType === 'expired') {
            $batches = MedicineBatch::with(['medicine', 'supplier:id,name'])
                ->where('quantity', '>', 0)
                ->where('expiry_date', '<=', now())
                ->orderBy('expiry_date', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $batches,
            ]);
        }
        
        // For expiring soon
        if ($alertType === 'expiring_soon') {
            $days = (int) $request->get('days', 30);
            $batches = MedicineBatch::with(['medicine', 'supplier:id,name'])
                ->where('quantity', '>', 0)
                ->where('expiry_date', '>', now())
                ->where('expiry_date', '<=', now()->addDays($days))
                ->orderBy('expiry_date', 'asc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $batches,
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Invalid alert type',
        ], 400);
    }

    /**
     * Get expired medicines/batches
     */
    public function expired(): JsonResponse
    {
        $batches = MedicineBatch::with(['medicine', 'supplier:id,name'])
            ->where('quantity', '>', 0)
            ->where('expiry_date', '<=', now())
            ->orderBy('expiry_date', 'desc')
            ->get();

        $totalValue = $batches->sum(function($batch) {
            return $batch->quantity * $batch->cost_price_per_unit;
        });

        return response()->json([
            'batches' => $batches,
            'total_expired_quantity' => $batches->sum('quantity'),
            'total_value_loss' => round($totalValue, 2),
        ]);
    }

    /**
     * Get medicines/batches expiring soon (within specified days)
     */
    public function expiringSoon(Request $request): JsonResponse
    {
        $days = (int) $request->get('days', 30);

        $batches = MedicineBatch::with(['medicine', 'supplier:id,name'])
            ->where('quantity', '>', 0)
            ->where('expiry_date', '>', now())
            ->where('expiry_date', '<=', now()->addDays($days))
            ->orderBy('expiry_date', 'asc')
            ->get()
            ->map(function($batch) {
                $batch->days_until_expiry = now()->diffInDays($batch->expiry_date);
                return $batch;
            });

        return response()->json([
            'batches' => $batches,
            'total_quantity' => $batches->sum('quantity'),
        ]);
    }

    /**
     * Get inventory valuation
     */
    public function valuation(): JsonResponse
    {
        $batches = MedicineBatch::with('medicine:id,name')
            ->where('quantity', '>', 0)
            ->where('expiry_date', '>', now())
            ->get();

        $costValue = $batches->sum(function($batch) {
            return $batch->quantity * $batch->cost_price_per_unit;
        });

        $sellingValue = $batches->sum(function($batch) {
            return $batch->quantity * $batch->selling_price_per_unit;
        });

        $potentialProfit = $sellingValue - $costValue;

        return response()->json([
            'total_items' => $batches->count(),
            'total_quantity' => $batches->sum('quantity'),
            'cost_value' => round($costValue, 2),
            'selling_value' => round($sellingValue, 2),
            'potential_profit' => round($potentialProfit, 2),
            'profit_margin' => $sellingValue > 0 ? round(($potentialProfit / $sellingValue) * 100, 2) : 0,
        ]);
    }
}
