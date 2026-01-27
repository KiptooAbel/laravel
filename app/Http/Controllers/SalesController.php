<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\SalesService;
use App\Http\Requests\CreateSaleRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class SalesController extends Controller
{
    protected $salesService;

    public function __construct(SalesService $salesService)
    {
        $this->salesService = $salesService;
    }

    /**
     * Display a listing of sales.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Sale::with(['items.medicine', 'user', 'payments'])
                ->orderBy('created_at', 'desc');

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by date range
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->dateRange($request->start_date, $request->end_date);
            }

            // Filter by user (cashier/pharmacist)
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by payment method
            if ($request->has('payment_method')) {
                $query->where('payment_method', $request->payment_method);
            }

            // Search by sale number or customer name
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('sale_number', 'like', "%{$search}%")
                      ->orWhere('customer_name', 'like', "%{$search}%")
                      ->orWhere('customer_phone', 'like', "%{$search}%");
                });
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $sales = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $sales,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sales.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created sale.
     */
    public function store(CreateSaleRequest $request): JsonResponse
    {
        try {
            // Merge user_id from authenticated user into validated data
            $data = array_merge($request->validated(), [
                'user_id' => $request->user()->id,
            ]);
            
            $sale = $this->salesService->processSale($data);

            return response()->json([
                'success' => true,
                'message' => 'Sale completed successfully.',
                'data' => $sale,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process sale.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Display the specified sale.
     */
    public function show(Sale $sale): JsonResponse
    {
        try {
            $sale->load(['items.medicine', 'items.batch', 'user', 'payments', 'voidedByUser']);

            return response()->json([
                'success' => true,
                'data' => $sale,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sale details.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Void a sale.
     */
    public function void(Request $request, Sale $sale): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|min:10|max:500',
        ]);

        try {
            // Check permission (only owner or pharmacist can void sales)
            if (!$request->user()->hasRole(['owner', 'pharmacist'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to void sales.',
                ], 403);
            }

            $voidedSale = $this->salesService->voidSale(
                $sale,
                $request->user()->id,
                $request->reason
            );

            return response()->json([
                'success' => true,
                'message' => 'Sale voided successfully.',
                'data' => $voidedSale,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to void sale.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get receipt data for a sale.
     */
    public function receipt(Sale $sale): JsonResponse
    {
        try {
            $receiptData = $this->salesService->generateReceipt($sale);

            return response()->json([
                'success' => true,
                'data' => $receiptData,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate receipt.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get today's sales summary.
     */
    public function todaySummary(Request $request): JsonResponse
    {
        try {
            $today = now()->startOfDay();
            
            $summary = [
                'total_sales' => Sale::completed()
                    ->whereDate('created_at', $today)
                    ->count(),
                'total_revenue' => Sale::completed()
                    ->whereDate('created_at', $today)
                    ->sum('total'),
                'cash_sales' => Sale::completed()
                    ->whereDate('created_at', $today)
                    ->where('payment_method', 'cash')
                    ->sum('total'),
                'mpesa_sales' => Sale::completed()
                    ->whereDate('created_at', $today)
                    ->where('payment_method', 'mpesa')
                    ->sum('total'),
                'card_sales' => Sale::completed()
                    ->whereDate('created_at', $today)
                    ->where('payment_method', 'card')
                    ->sum('total'),
                'total_vat' => Sale::completed()
                    ->whereDate('created_at', $today)
                    ->sum('vat_amount'),
            ];

            return response()->json([
                'success' => true,
                'data' => $summary,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sales summary.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
