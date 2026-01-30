<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    /**
     * Get all suppliers with optional filters
     */
    public function index(Request $request)
    {
        $query = Supplier::query();

        // Search by name, contact person, phone, email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('contact_person', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by status (support both 'is_active' and 'status' parameters)
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        } elseif ($request->has('status')) {
            $isActive = $request->status === 'active' ? 1 : 0;
            $query->where('is_active', $isActive);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $suppliers = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $suppliers,
        ]);
    }

    /**
     * Get a single supplier
     */
    public function show($id)
    {
        $supplier = Supplier::findOrFail($id);
        
        return response()->json([
            'supplier' => $supplier,
        ]);
    }

    /**
     * Create a new supplier
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'tax_id' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $supplier = Supplier::create($validated);

        return response()->json([
            'message' => 'Supplier created successfully',
            'supplier' => $supplier,
        ], 201);
    }

    /**
     * Update a supplier
     */
    public function update(Request $request, $id)
    {
        $supplier = Supplier::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'tax_id' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $supplier->update($validated);

        return response()->json([
            'message' => 'Supplier updated successfully',
            'supplier' => $supplier,
        ]);
    }

    /**
     * Toggle supplier status
     */
    public function toggleStatus($id)
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->is_active = !$supplier->is_active;
        $supplier->save();

        return response()->json([
            'message' => 'Supplier status updated successfully',
            'supplier' => $supplier,
        ]);
    }

    /**
     * Delete a supplier
     */
    public function destroy($id)
    {
        $supplier = Supplier::findOrFail($id);

        // Check if supplier has related medicine batches
        if ($supplier->medicineBatches()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete supplier with existing medicine batches',
            ], 422);
        }

        $supplier->delete();

        return response()->json([
            'message' => 'Supplier deleted successfully',
        ]);
    }
}
