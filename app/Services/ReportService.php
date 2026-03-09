<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\SaleItem;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportService
{
    /**
     * Get daily sales report
     */
    public function getDailySalesReport($startDate, $endDate)
    {
        $sales = Sale::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->with(['items.medicine', 'items.batch', 'user'])
            ->get();

        // Calculate profit
        $totalCost = 0;
        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $cost = $item->batch ? ($item->batch->cost_price_per_unit * $item->quantity) : 0;
                $totalCost += $cost;
            }
        }

        $totalRevenue = $sales->sum('subtotal');
        $totalProfit = $totalRevenue - $totalCost;

        // Get expenses for the period
        $expenses = Expense::whereBetween('expense_date', [$startDate, $endDate])->get();
        $totalExpenses = $expenses->sum('amount');
        $netProfit = $totalProfit - $totalExpenses;

        $grouped = $sales->groupBy(function ($sale) {
            return Carbon::parse($sale->created_at)->format('Y-m-d');
        });

        $report = [];
        foreach ($grouped as $date => $daySales) {
            $report[] = [
                'date' => $date,
                'total_sales' => $daySales->count(),
                'total_revenue' => $daySales->sum('subtotal'),
                'total_discount' => $daySales->sum('discount'),
                'total_tax' => $daySales->sum('vat_amount'),
                'net_amount' => $daySales->sum('total'),
                'payment_methods' => $this->groupByPaymentMethod($daySales),
            ];
        }

        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'summary' => [
                'total_cost' => round($totalCost, 2),
                'gross_profit' => round($totalProfit, 2),
                'total_expenses' => round($totalExpenses, 2),
                'net_profit' => round($netProfit, 2),
                'total_discount' => round($sales->sum('discount'), 2),
                'total_tax' => round($sales->sum('vat_amount'), 2),
                'net_amount' => round($sales->sum('total'), 2),
                'total_profit' => round($totalProfit, 2),
                'total_cost' => round($totalCost, 2),
                'average_sale' => $sales->count() > 0 ? round($sales->sum('total') / $sales->count(), 2) : 0,
            ],
            'daily_breakdown' => $report,
        ];
    }

    /**
     * Get profit report
     */
    public function getProfitReport($startDate, $endDate)
    {
        $saleItems = SaleItem::whereHas('sale', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'completed');
        })->with(['medicine', 'batch', 'sale'])->get();

        $totalRevenue = 0;
        $totalCost = 0;
        $items = [];

        foreach ($saleItems as $item) {
            $revenue = $item->subtotal;
            $cost = $item->batch ? ($item->batch->cost_price_per_unit * $item->quantity) : 0;
            $profit = $revenue - $cost;

            $totalRevenue += $revenue;
            $totalCost += $cost;

            $items[] = [
                'medicine_id' => $item->medicine_id,
                'medicine_name' => $item->medicine->name ?? 'N/A',
                'quantity_sold' => $item->quantity,
                'revenue' => $revenue,
                'cost' => $cost,
                'profit' => $profit,
                'profit_margin' => $revenue > 0 ? ($profit / $revenue) * 100 : 0,
            ];
        }

        // Group by medicine
        $groupedItems = collect($items)->groupBy('medicine_id')->map(function ($group) {
            return [
                'medicine_id' => $group->first()['medicine_id'],
                'medicine_name' => $group->first()['medicine_name'],
                'quantity_sold' => $group->sum('quantity_sold'),
                'revenue' => $group->sum('revenue'),
                'cost' => $group->sum('cost'),
                'profit' => $group->sum('profit'),
                'profit_margin' => $group->sum('revenue') > 0 ? ($group->sum('profit') / $group->sum('revenue')) * 100 : 0,
            ];
        })->values()->sortByDesc('profit')->take(20)->values()->toArray();

        $totalProfit = $totalRevenue - $totalCost;
        $profitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

        // Get expenses for the period
        $expenses = Expense::whereBetween('expense_date', [$startDate, $endDate])->get();
        $totalExpenses = $expenses->sum('amount');
        
        // Group expenses by type
        $expensesByType = $expenses->groupBy('expense_type')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total' => $group->sum('amount'),
            ];
        });

        // Calculate net profit (profit minus expenses)
        $netProfit = $totalProfit - $totalExpenses;
        $netProfitMargin = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0;

        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_cost' => $totalCost,
                'gross_profit' => $totalProfit,
                'gross_profit_margin' => round($profitMargin, 2),
                'total_expenses' => $totalExpenses,
                'net_profit' => $netProfit,
                'net_profit_margin' => round($netProfitMargin, 2),
            ],
            'expenses_by_type' => $expensesByType,
            'top_profitable_medicines' => $groupedItems,
        ];
    }

    /**
     * Get stock valuation report
     */
    public function getStockValuationReport()
    {
        $medicines = Medicine::with(['batches' => function ($query) {
            $query->where('quantity', '>', 0)
                ->where(function ($q) {
                    $q->whereNull('expiry_date')
                        ->orWhere('expiry_date', '>', now());
                });
        }])->get();

        $totalValuationCost = 0;
        $totalValuationSelling = 0;
        $lowStockCount = 0;
        $items = [];

        foreach ($medicines as $medicine) {
            $stockQuantity = $medicine->batches->sum('quantity');
            
            if ($stockQuantity > 0) {
                // Calculate weighted average cost
                $totalCost = 0;
                $totalQuantity = 0;
                
                foreach ($medicine->batches as $batch) {
                    $totalCost += $batch->cost_price_per_unit * $batch->quantity;
                    $totalQuantity += $batch->quantity;
                }
                
                $avgCostPrice = $totalQuantity > 0 ? $totalCost / $totalQuantity : 0;
                $valuationCost = $totalCost;
                $valuationSelling = $medicine->unit_price * $stockQuantity;

                $totalValuationCost += $valuationCost;
                $totalValuationSelling += $valuationSelling;

                // Check if low stock
                if ($stockQuantity <= $medicine->reorder_level) {
                    $lowStockCount++;
                }

                $items[] = [
                    'medicine_id' => $medicine->id,
                    'medicine_name' => $medicine->name,
                    'generic_name' => $medicine->generic_name,
                    'stock_quantity' => $stockQuantity,
                    'avg_cost_price' => round($avgCostPrice, 2),
                    'selling_price' => $medicine->unit_price,
                    'valuation_at_cost' => round($valuationCost, 2),
                    'valuation_at_selling' => round($valuationSelling, 2),
                    'potential_profit' => round($valuationSelling - $valuationCost, 2),
                ];
            }
        }

        return [
            'generated_at' => now()->toDateTimeString(),
            'summary' => [
                'total_medicines' => count($items), // Medicines with stock > 0
                'total_medicines_all' => $medicines->count(), // All medicines (for reference)
                'total_stock_quantity' => collect($items)->sum('stock_quantity'),
                'total_value' => round($totalValuationCost, 2),
                'total_valuation_at_cost' => round($totalValuationCost, 2),
                'total_valuation_at_selling' => round($totalValuationSelling, 2),
                'potential_profit' => round($totalValuationSelling - $totalValuationCost, 2),
                'low_stock_count' => $lowStockCount,
            ],
            'items' => collect($items)->sortByDesc('valuation_at_cost')->values(),
        ];
    }

    /**
     * Get expired items report
     */
    public function getExpiredItemsReport()
    {
        $expiredBatches = MedicineBatch::where('expiry_date', '<=', now())
            ->where('quantity', '>', 0)
            ->with('medicine')
            ->get();

        $totalLoss = 0;
        $items = [];

        foreach ($expiredBatches as $batch) {
            $loss = $batch->cost_price_per_unit * $batch->quantity;
            $totalLoss += $loss;

            $items[] = [
                'batch_number' => $batch->batch_number,
                'medicine_id' => $batch->medicine_id,
                'medicine_name' => $batch->medicine->name ?? 'N/A',
                'generic_name' => $batch->medicine->generic_name ?? 'N/A',
                'quantity' => $batch->quantity,
                'cost_price' => $batch->cost_price_per_unit,
                'expiry_date' => $batch->expiry_date,
                'days_expired' => Carbon::parse($batch->expiry_date)->diffInDays(now()),
                'loss_amount' => round($loss, 2),
            ];
        }

        return [
            'generated_at' => now()->toDateTimeString(),
            'summary' => [
                'total_expired' => count($items),
                'total_expired_batches' => count($items),
                'total_expired_quantity' => (int) collect($items)->sum('quantity'),
                'total_loss_amount' => round($totalLoss, 2),
            ],
            'items' => collect($items)->sortByDesc('loss_amount')->values(),
        ];
    }

    /**
     * Get expiring soon report
     */
    public function getExpiringSoonReport($days = 90)
    {
        $expiryThreshold = now()->addDays((int) $days);

        $expiringBatches = MedicineBatch::where('expiry_date', '>', now())
            ->where('expiry_date', '<=', $expiryThreshold)
            ->where('quantity', '>', 0)
            ->with('medicine')
            ->get();

        $items = [];

        foreach ($expiringBatches as $batch) {
            $potentialLoss = $batch->cost_price_per_unit * $batch->quantity;
            $daysUntilExpiry = now()->diffInDays(Carbon::parse($batch->expiry_date));

            $items[] = [
                'batch_number' => $batch->batch_number,
                'medicine_id' => $batch->medicine_id,
                'medicine_name' => $batch->medicine->name ?? 'N/A',
                'generic_name' => $batch->medicine->generic_name ?? 'N/A',
                'quantity' => $batch->quantity,
                'cost_price' => $batch->cost_price_per_unit,
                'selling_price' => $batch->medicine->unit_price ?? 0,
                'expiry_date' => $batch->expiry_date,
                'days_until_expiry' => $daysUntilExpiry,
                'potential_loss' => round($potentialLoss, 2),
                'urgency' => $this->getUrgencyLevel($daysUntilExpiry),
            ];
        }

        return [
            'generated_at' => now()->toDateTimeString(),
            'threshold_days' => $days,
            'summary' => [
                'total_expiring' => count($items),
                'total_batches_expiring_soon' => count($items), // Match Flutter model
                'total_quantity' => (int) collect($items)->sum('quantity'),
                'potential_loss' => round(collect($items)->sum('potential_loss'), 2),
            ],
            'items' => collect($items)->sortBy('days_until_expiry')->values(),
        ];
    }

    /**
     * Get top selling medicines
     */
    public function getTopSellingMedicines($startDate, $endDate, $limit = 10)
    {
        $topSellers = SaleItem::whereHas('sale', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'completed');
        })
            ->select('medicine_id', DB::raw('SUM(quantity) as total_quantity'), DB::raw('SUM(subtotal) as total_revenue'))
            ->groupBy('medicine_id')
            ->orderBy('total_quantity', 'desc')
            ->limit($limit)
            ->with('medicine')
            ->get();

        return $topSellers->map(function ($item) {
            return [
                'medicine_id' => $item->medicine_id,
                'medicine_name' => $item->medicine->name ?? 'N/A',
                'generic_name' => $item->medicine->generic_name ?? 'N/A',
                'total_quantity' => $item->total_quantity,
                'total_revenue' => round($item->total_revenue, 2),
            ];
        });
    }

    /**
     * Group sales by payment method
     */
    private function groupByPaymentMethod($sales)
    {
        $grouped = $sales->groupBy(function ($sale) {
            return $sale->payment_method ?? 'unknown';
        });

        $result = [];
        foreach ($grouped as $method => $methodSales) {
            $result[$method] = [
                'count' => $methodSales->count(),
                'amount' => $methodSales->sum('total'),
            ];
        }

        return $result;
    }

    /**
     * Get urgency level based on days until expiry
     */
    private function getUrgencyLevel($days)
    {
        if ($days <= 30) {
            return 'critical';
        } elseif ($days <= 60) {
            return 'high';
        } else {
            return 'medium';
        }
    }
}
