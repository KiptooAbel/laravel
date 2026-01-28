<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MedicineController extends Controller
{
    /**
     * Display a listing of medicines
     */
    public function index(Request $request): JsonResponse
    {
        $query = Medicine::with(['batches' => function($q) {
            $q->where('quantity', '>', 0)
              ->where('expiry_date', '>', now());
        }]);

        // Search by name, generic name, or barcode
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('generic_name', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by low stock
        if ($request->boolean('low_stock')) {
            $query->whereRaw('(SELECT SUM(quantity) FROM medicine_batches 
                WHERE medicine_batches.medicine_id = medicines.id 
                AND medicine_batches.quantity > 0 
                AND medicine_batches.expiry_date > NOW()) <= medicines.reorder_level');
        }

        $medicines = $query->paginate($request->get('per_page', 15));

        return response()->json($medicines);
    }

    /**
     * Store a newly created medicine
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'generic_name' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|unique:medicines,barcode',
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'manufacturer' => 'nullable|string|max:255',
            'unit_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'reorder_level' => 'required|integer|min:0',
            'unit_of_measure' => 'required|string|max:50',
            'requires_prescription' => 'boolean',
            'is_controlled' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $medicine = Medicine::create($validated);

        return response()->json([
            'message' => 'Medicine created successfully',
            'medicine' => $medicine->load('batches')
        ], 201);
    }

    /**
     * Display the specified medicine
     */
    public function show(Medicine $medicine): JsonResponse
    {
        $medicine->load([
            'batches' => function($q) {
                $q->orderBy('expiry_date', 'asc');
            },
            'batches.supplier',
            'stockMovements' => function($q) {
                $q->with('user:id,name')
                  ->latest()
                  ->limit(20);
            }
        ]);

        return response()->json([
            'medicine' => $medicine,
            'low_stock' => $medicine->isLowStock(),
            'has_expired' => $medicine->hasExpiredBatches(),
            'expiring_soon' => $medicine->expiringSoonBatches()->count(),
        ]);
    }

    /**
     * Update the specified medicine
     */
    public function update(Request $request, Medicine $medicine): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'generic_name' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|unique:medicines,barcode,' . $medicine->id,
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'manufacturer' => 'nullable|string|max:255',
            'unit_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'reorder_level' => 'sometimes|required|integer|min:0',
            'unit_of_measure' => 'sometimes|required|string|max:50',
            'requires_prescription' => 'boolean',
            'is_controlled' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $medicine->update($validated);

        return response()->json([
            'message' => 'Medicine updated successfully',
            'medicine' => $medicine->fresh()->load('batches')
        ]);
    }

    /**
     * Remove the specified medicine (soft delete)
     */
    public function destroy(Medicine $medicine): JsonResponse
    {
        // Check if medicine has any stock
        if ($medicine->total_stock > 0) {
            return response()->json([
                'message' => 'Cannot delete medicine with existing stock'
            ], 422);
        }

        $medicine->delete();

        return response()->json([
            'message' => 'Medicine deleted successfully'
        ]);
    }

    /**
     * Get batches for a specific medicine
     */
    public function batches(Medicine $medicine): JsonResponse
    {
        $batches = $medicine->batches()
            ->with('supplier:id,name')
            ->orderBy('expiry_date', 'asc')
            ->get();

        return response()->json([
            'batches' => $batches
        ]);
    }

    /**
     * Search medicine by barcode
     */
    public function searchByBarcode(Request $request): JsonResponse
    {
        $request->validate([
            'barcode' => 'required|string'
        ]);

        $medicine = Medicine::where('barcode', $request->barcode)
            ->with(['activeBatches' => function($q) {
                $q->limit(1); // Get oldest batch for FIFO
            }])
            ->first();

        if (!$medicine) {
            return response()->json([
                'message' => 'Medicine not found'
            ], 404);
        }

        return response()->json([
            'medicine' => $medicine
        ]);
    }
}
