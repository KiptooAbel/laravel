<?php

namespace App\Services;

use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Deduct stock using FIFO (First In, First Out) method
     * Returns array of batches used and quantities deducted
     *
     * @param int $medicineId
     * @param int $quantityNeeded
     * @return array ['success' => bool, 'batches' => array, 'message' => string]
     */
    public function deductStockFifo(int $medicineId, int $quantityNeeded): array
    {
        // Get available batches ordered by expiry date (FIFO)
        $batches = MedicineBatch::where('medicine_id', $medicineId)
            ->where('quantity', '>', 0)
            ->where('expiry_date', '>', now())
            ->orderBy('expiry_date', 'asc') // Oldest first
            ->orderBy('received_date', 'asc') // Then by received date
            ->get();

        // Check if enough stock is available
        $totalAvailable = $batches->sum('quantity');
        if ($totalAvailable < $quantityNeeded) {
            return [
                'success' => false,
                'batches' => [],
                'message' => "Insufficient stock. Available: {$totalAvailable}, Required: {$quantityNeeded}"
            ];
        }

        $batchesUsed = [];
        $remainingQuantity = $quantityNeeded;

        foreach ($batches as $batch) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $quantityFromThisBatch = min($batch->quantity, $remainingQuantity);
            
            $batchesUsed[] = [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'quantity_used' => $quantityFromThisBatch,
                'cost_price' => $batch->cost_price_per_unit,
                'selling_price' => $batch->selling_price_per_unit,
            ];

            $remainingQuantity -= $quantityFromThisBatch;
        }

        return [
            'success' => true,
            'batches' => $batchesUsed,
            'message' => 'Stock allocation successful'
        ];
    }

    /**
     * Actually deduct stock from batches and record movements
     *
     * @param int $medicineId
     * @param int $quantity
     * @param string $type (sale, adjustment, etc.)
     * @param int $userId
     * @param string|null $referenceType
     * @param int|null $referenceId
     * @param string|null $notes
     * @return array
     */
    public function deductAndRecord(
        int $medicineId,
        int $quantity,
        string $type,
        int $userId,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null
    ): array {
        DB::beginTransaction();
        try {
            // Get batches to deduct from using FIFO
            $allocationResult = $this->deductStockFifo($medicineId, $quantity);

            if (!$allocationResult['success']) {
                throw new \Exception($allocationResult['message']);
            }

            $medicine = Medicine::findOrFail($medicineId);
            $movements = [];

            // Deduct from each batch and record movement
            foreach ($allocationResult['batches'] as $batchData) {
                $batch = MedicineBatch::findOrFail($batchData['batch_id']);
                $batch->quantity -= $batchData['quantity_used'];
                $batch->save();

                // Record stock movement
                $movement = StockMovement::create([
                    'medicine_id' => $medicineId,
                    'batch_id' => $batch->id,
                    'type' => $type,
                    'quantity' => -$batchData['quantity_used'], // Negative for deduction
                    'balance_after' => $medicine->fresh()->total_stock,
                    'unit_price' => $batchData['selling_price'],
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'notes' => $notes,
                    'user_id' => $userId,
                ]);

                $movements[] = $movement;
            }

            DB::commit();

            return [
                'success' => true,
                'batches_used' => $allocationResult['batches'],
                'movements' => $movements,
                'new_balance' => $medicine->fresh()->total_stock,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Add stock to a medicine (create new batch or add to existing)
     *
     * @param array $batchData
     * @param int $userId
     * @return array
     */
    public function addStock(array $batchData, int $userId): array
    {
        DB::beginTransaction();
        try {
            $batch = MedicineBatch::create($batchData);

            // Record stock movement
            $movement = StockMovement::create([
                'medicine_id' => $batch->medicine_id,
                'batch_id' => $batch->id,
                'type' => 'purchase',
                'quantity' => $batch->quantity,
                'balance_after' => Medicine::find($batch->medicine_id)->fresh()->total_stock,
                'unit_price' => $batch->cost_price_per_unit,
                'notes' => $batchData['notes'] ?? 'Stock added',
                'user_id' => $userId,
            ]);

            DB::commit();

            return [
                'success' => true,
                'batch' => $batch,
                'movement' => $movement,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get total available stock for a medicine
     */
    public function getAvailableStock(int $medicineId): int
    {
        return MedicineBatch::where('medicine_id', $medicineId)
            ->where('quantity', '>', 0)
            ->where('expiry_date', '>', now())
            ->sum('quantity');
    }

    /**
     * Calculate weighted average cost for a medicine
     */
    public function getWeightedAverageCost(int $medicineId): float
    {
        $batches = MedicineBatch::where('medicine_id', $medicineId)
            ->where('quantity', '>', 0)
            ->where('expiry_date', '>', now())
            ->get();

        $totalCost = $batches->sum(function($batch) {
            return $batch->quantity * $batch->cost_price_per_unit;
        });

        $totalQuantity = $batches->sum('quantity');

        return $totalQuantity > 0 ? $totalCost / $totalQuantity : 0;
    }

    /**
     * Get medicines that need reordering
     */
    public function getMedicinesNeedingReorder(): array
    {
        return Medicine::whereRaw('(SELECT SUM(quantity) FROM medicine_batches 
            WHERE medicine_batches.medicine_id = medicines.id 
            AND medicine_batches.quantity > 0 
            AND medicine_batches.expiry_date > NOW()) <= medicines.reorder_level')
            ->with('activeBatches')
            ->get()
            ->map(function($medicine) {
                return [
                    'medicine' => $medicine,
                    'current_stock' => $medicine->total_stock,
                    'reorder_level' => $medicine->reorder_level,
                    'suggested_order_quantity' => max(50, $medicine->reorder_level * 2), // Example logic
                ];
            })
            ->toArray();
    }

    /**
     * Get expiring batches within specified days
     */
    public function getExpiringBatches(int $days = 30): array
    {
        return MedicineBatch::with(['medicine:id,name,generic_name', 'supplier:id,name'])
            ->where('quantity', '>', 0)
            ->where('expiry_date', '>', now())
            ->where('expiry_date', '<=', now()->addDays($days))
            ->orderBy('expiry_date', 'asc')
            ->get()
            ->map(function($batch) {
                return [
                    'batch' => $batch,
                    'days_until_expiry' => now()->diffInDays($batch->expiry_date),
                    'value_at_risk' => $batch->quantity * $batch->cost_price_per_unit,
                ];
            })
            ->toArray();
    }

    /**
     * Calculate inventory value
     */
    public function calculateInventoryValue(): array
    {
        $batches = MedicineBatch::where('quantity', '>', 0)
            ->where('expiry_date', '>', now())
            ->get();

        $costValue = $batches->sum(function($batch) {
            return $batch->quantity * $batch->cost_price_per_unit;
        });

        $retailValue = $batches->sum(function($batch) {
            return $batch->quantity * $batch->selling_price_per_unit;
        });

        return [
            'total_batches' => $batches->count(),
            'total_quantity' => $batches->sum('quantity'),
            'cost_value' => round($costValue, 2),
            'retail_value' => round($retailValue, 2),
            'potential_profit' => round($retailValue - $costValue, 2),
            'markup_percentage' => $costValue > 0 ? round((($retailValue - $costValue) / $costValue) * 100, 2) : 0,
        ];
    }
}
