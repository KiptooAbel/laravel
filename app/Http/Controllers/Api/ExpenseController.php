<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ExpenseController extends Controller
{
    /**
     * Get all expenses with pagination and filters
     */
    public function index(Request $request)
    {
        $query = Expense::with('recordedBy');

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('expense_date', '>=', Carbon::parse($request->start_date));
        }

        if ($request->has('end_date')) {
            $query->where('expense_date', '<=', Carbon::parse($request->end_date));
        }

        // Filter by expense type
        if ($request->has('expense_type') && $request->expense_type !== 'all') {
            $query->where('expense_type', $request->expense_type);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'expense_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        if ($request->has('all') && $request->all === 'true') {
            $expenses = $query->get();
            return response()->json([
                'success' => true,
                'data' => $expenses,
            ]);
        }

        $expenses = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $expenses,
        ]);
    }

    /**
     * Get expense statistics
     */
    public function statistics(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();

        $expenses = Expense::whereBetween('expense_date', [$startDate, $endDate])->get();

        $byType = $expenses->groupBy('expense_type')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total' => $group->sum('amount'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'total_expenses' => $expenses->sum('amount'),
                'total_count' => $expenses->count(),
                'by_type' => $byType,
                'average_expense' => $expenses->count() > 0 ? $expenses->avg('amount') : 0,
            ],
        ]);
    }

    /**
     * Store a new expense
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'expense_type' => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'expense_date' => 'required|date',
            'payment_method' => 'nullable|string|max:255',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $expense = Expense::create([
            'expense_type' => $request->expense_type,
            'description' => $request->description,
            'amount' => $request->amount,
            'expense_date' => $request->expense_date,
            'payment_method' => $request->payment_method,
            'reference_number' => $request->reference_number,
            'notes' => $request->notes,
            'recorded_by' => auth()->id(),
        ]);

        $expense->load('recordedBy');

        return response()->json([
            'success' => true,
            'message' => 'Expense recorded successfully',
            'data' => $expense,
        ], 201);
    }

    /**
     * Get a single expense
     */
    public function show($id)
    {
        $expense = Expense::with('recordedBy')->find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $expense,
        ]);
    }

    /**
     * Update an expense
     */
    public function update(Request $request, $id)
    {
        $expense = Expense::find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'expense_type' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string|max:255',
            'amount' => 'sometimes|required|numeric|min:0',
            'expense_date' => 'sometimes|required|date',
            'payment_method' => 'nullable|string|max:255',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $expense->update($request->all());
        $expense->load('recordedBy');

        return response()->json([
            'success' => true,
            'message' => 'Expense updated successfully',
            'data' => $expense,
        ]);
    }

    /**
     * Delete an expense
     */
    public function destroy($id)
    {
        $expense = Expense::find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found',
            ], 404);
        }

        $expense->delete();

        return response()->json([
            'success' => true,
            'message' => 'Expense deleted successfully',
        ]);
    }

    /**
     * Get available expense types
     */
    public function types()
    {
        return response()->json([
            'success' => true,
            'data' => Expense::expenseTypes(),
        ]);
    }
}
