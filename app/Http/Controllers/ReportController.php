<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReportController extends Controller
{
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Get daily sales report
     */
    public function dailySales(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();

        $report = $this->reportService->getDailySalesReport($startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Get profit report
     */
    public function profit(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();

        $report = $this->reportService->getProfitReport($startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Get stock valuation report
     */
    public function stockValuation()
    {
        $report = $this->reportService->getStockValuationReport();

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Get expired items report
     */
    public function expiredItems()
    {
        $report = $this->reportService->getExpiredItemsReport();

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Get expiring soon items
     */
    public function expiringSoon(Request $request)
    {
        $days = $request->input('days', 90);

        $request->validate([
            'days' => 'integer|min:1|max:365',
        ]);

        $report = $this->reportService->getExpiringSoonReport($days);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Get top selling medicines
     */
    public function topSelling(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'limit' => 'integer|min:1|max:50',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();
        $limit = $request->input('limit', 10);

        $topSellers = $this->reportService->getTopSellingMedicines($startDate, $endDate, $limit);

        return response()->json([
            'success' => true,
            'data' => $topSellers,
        ]);
    }

    /**
     * Get comprehensive dashboard report
     */
    public function dashboard(Request $request)
    {
        $startDate = $request->input('start_date') 
            ? Carbon::parse($request->start_date)->startOfDay()
            : Carbon::now()->startOfMonth();
        
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->end_date)->endOfDay()
            : Carbon::now()->endOfDay();

        // Get all reports for dashboard
        $salesReport = $this->reportService->getDailySalesReport($startDate, $endDate);
        $profitReport = $this->reportService->getProfitReport($startDate, $endDate);
        $stockValuation = $this->reportService->getStockValuationReport();
        $expiredItems = $this->reportService->getExpiredItemsReport();
        $expiringSoon = $this->reportService->getExpiringSoonReport(30);
        $topSelling = $this->reportService->getTopSellingMedicines($startDate, $endDate, 5);

        return response()->json([
            'success' => true,
            'data' => [
                'sales' => $salesReport['summary'],
                'profit' => $profitReport['summary'],
                'stock_valuation' => $stockValuation['summary'],
                'expired' => $expiredItems['summary'],
                'expiring_soon' => $expiringSoon['summary'],
                'top_selling' => $topSelling,
            ],
        ]);
    }
}
