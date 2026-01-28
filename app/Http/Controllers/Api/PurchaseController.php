<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PurchaseController extends Controller
{
    /**
     * Get all purchases with optional filters
     */
    public function index(Request $request)
    {
        $query = Purchase::with(['supplier', 'user', 'items.medicine']);

        // Search by purchase number
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('purchase_number', 'like', "%{$search}%");
        }

        // Filter by supplier
        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Filter by payment status
        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('purchase_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('purchase_date', '<=', $request->end_date);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $purchases = $query->orderBy('purchase_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($purchases);
    }

    /**
     * Get a single purchase with details
     */
    public function show($id)
    {
        $purchase = Purchase::with(['supplier', 'user', 'items.medicine'])
            ->findOrFail($id);

        return response()->json([
            'purchase' => $purchase,
        ]);
    }

    /**
     * Create a new purchase and update inventory
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'required|exists:medicines,id',
            'items.*.batch_number' => 'required|string',
            'items.*.expiry_date' => 'required|date|after:today',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.selling_price' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Generate purchase number
            $purchaseNumber = Purchase::generatePurchaseNumber();

            // Calculate totals
            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $subtotal += $item['quantity'] * $item['unit_cost'];
            }

            // Create purchase
            $purchase = Purchase::create([
                'purchase_number' => $purchaseNumber,
                'supplier_id' => $validated['supplier_id'],
                'user_id' => Auth::id(),
                'purchase_date' => $validated['purchase_date'],
                'subtotal' => $subtotal,
                'tax' => 0,
                'discount' => 0,
                'total' => $subtotal,
                'payment_status' => 'pending',
                'paid_amount' => 0,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Create purchase items and update inventory
            foreach ($validated['items'] as $itemData) {
                $itemSubtotal = $itemData['quantity'] * $itemData['unit_cost'];

                // Create purchase item
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'medicine_id' => $itemData['medicine_id'],
                    'batch_number' => $itemData['batch_number'],
                    'expiry_date' => $itemData['expiry_date'],
                    'quantity' => $itemData['quantity'],
                    'unit_cost' => $itemData['unit_cost'],
                    'selling_price' => $itemData['selling_price'],
                    'subtotal' => $itemSubtotal,
                ]);

                // Create or update medicine batch
                $batch = MedicineBatch::firstOrCreate(
                    [
                        'medicine_id' => $itemData['medicine_id'],
                        'batch_number' => $itemData['batch_number'],
                    ],
                    [
                        'supplier_id' => $validated['supplier_id'],
                        'expiry_date' => $itemData['expiry_date'],
                        'received_date' => $validated['purchase_date'],
                        'initial_quantity' => $itemData['quantity'],
                        'quantity' => 0,
                        'cost_price_per_unit' => $itemData['unit_cost'],
                        'selling_price_per_unit' => $itemData['selling_price'],
                    ]
                );

                // Update batch quantity
                $batch->quantity += $itemData['quantity'];
                $batch->cost_price_per_unit = $itemData['unit_cost'];
                $batch->selling_price_per_unit = $itemData['selling_price'];
                $batch->save();

                // Update medicine selling price if needed
                $medicine = Medicine::find($itemData['medicine_id']);
                $medicine->unit_price = $itemData['selling_price'];
                $medicine->save();
            }

            DB::commit();

            // Load relationships
            $purchase->load(['supplier', 'user', 'items.medicine']);

            return response()->json([
                'message' => 'Purchase recorded successfully',
                'purchase' => $purchase,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to record purchase',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Record a payment for a purchase
     */
    public function recordPayment(Request $request, $id)
    {
        $purchase = Purchase::findOrFail($id);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $amount = $validated['amount'];
        $balance = $purchase->balance;

        if ($amount > $balance) {
            return response()->json([
                'message' => 'Payment amount exceeds outstanding balance',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $purchase->recordPayment($amount);

            DB::commit();

            return response()->json([
                'message' => 'Payment recorded successfully',
                'purchase' => $purchase->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to record payment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get purchase statistics
     */
    public function statistics(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());

        $totalPurchases = Purchase::whereBetween('purchase_date', [$startDate, $endDate])
            ->count();

        $totalAmount = Purchase::whereBetween('purchase_date', [$startDate, $endDate])
            ->sum('total');

        $paidAmount = Purchase::whereBetween('purchase_date', [$startDate, $endDate])
            ->sum('paid_amount');

        $pendingAmount = $totalAmount - $paidAmount;

        $pendingPurchases = Purchase::where('payment_status', '!=', 'paid')
            ->count();

        return response()->json([
            'total_purchases' => $totalPurchases,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'pending_amount' => $pendingAmount,
            'pending_purchases' => $pendingPurchases,
        ]);
    }
}
