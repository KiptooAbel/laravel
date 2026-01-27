<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Payment;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Exception;

class SalesService
{
    /**
     * VAT rate (disabled)
     */
    const VAT_RATE = 0.0;

    /**
     * Process a new sale with FIFO batch deduction.
     *
     * @param array $data
     * @return Sale
     * @throws Exception
     */
    public function processSale(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            // Validate stock availability first
            $this->validateStockAvailability($data['items']);

            // Generate sale number
            $saleNumber = Sale::generateSaleNumber();

            // Calculate totals
            $totals = $this->calculateTotals($data['items'], $data['discount'] ?? 0);

            // Create sale record
            $sale = Sale::create([
                'sale_number' => $saleNumber,
                'user_id' => $data['user_id'],
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'subtotal' => $totals['subtotal'],
                'discount' => $data['discount'] ?? 0,
                'vat_amount' => $totals['vat_amount'],
                'total' => $totals['total'],
                'payment_method' => $data['payment_method'],
                'amount_tendered' => $data['amount_tendered'] ?? null,
                'change_given' => $data['change_given'] ?? null,
                'mpesa_transaction_id' => $data['mpesa_transaction_id'] ?? null,
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
            ]);

            // Process each sale item with FIFO
            foreach ($data['items'] as $item) {
                $this->processSaleItem($sale, $item);
            }

            // Create payment record(s)
            $this->createPaymentRecords($sale, $data);

            // Load relationships
            $sale->load(['items.medicine', 'items.batch', 'payments', 'user']);

            return $sale;
        });
    }

    /**
     * Validate that all items have sufficient stock.
     *
     * @param array $items
     * @throws Exception
     */
    protected function validateStockAvailability(array $items): void
    {
        foreach ($items as $item) {
            $medicine = Medicine::find($item['medicine_id']);
            
            if (!$medicine) {
                throw new Exception("Medicine with ID {$item['medicine_id']} not found.");
            }

            $availableStock = $medicine->batches()
                ->where('quantity', '>', 0)
                ->where('expiry_date', '>', now())
                ->sum('quantity');

            if ($availableStock < $item['quantity']) {
                throw new Exception("Insufficient stock for {$medicine->name}. Available: {$availableStock}, Required: {$item['quantity']}");
            }
        }
    }

    /**
     * Process a single sale item using FIFO.
     *
     * @param Sale $sale
     * @param array $itemData
     * @return void
     * @throws Exception
     */
    protected function processSaleItem(Sale $sale, array $itemData): void
    {
        $medicine = Medicine::find($itemData['medicine_id']);
        $remainingQuantity = $itemData['quantity'];
        
        // Get batches using FIFO (First In, First Out)
        $batches = MedicineBatch::where('medicine_id', $medicine->id)
            ->where('quantity', '>', 0)
            ->where('expiry_date', '>', now())
            ->orderBy('expiry_date', 'asc') // Oldest expiry first
            ->orderBy('created_at', 'asc')  // Oldest batch first
            ->get();

        if ($batches->isEmpty()) {
            throw new Exception("No available batches for {$medicine->name}");
        }

        // Deduct from batches using FIFO
        foreach ($batches as $batch) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $quantityFromThisBatch = min($remainingQuantity, $batch->quantity);

            // Calculate item totals
            $unitPrice = $itemData['unit_price'] ?? $medicine->selling_price;
            $itemDiscount = $itemData['discount'] ?? 0;
            $itemSubtotal = $quantityFromThisBatch * $unitPrice;
            $itemVat = ($itemSubtotal - $itemDiscount) * self::VAT_RATE;
            $itemTotal = ($itemSubtotal - $itemDiscount) + $itemVat;

            // Create sale item record
            SaleItem::create([
                'sale_id' => $sale->id,
                'medicine_id' => $medicine->id,
                'batch_id' => $batch->id,
                'medicine_name' => $medicine->name,
                'batch_number' => $batch->batch_number,
                'quantity' => $quantityFromThisBatch,
                'unit_price' => $unitPrice,
                'discount' => $itemDiscount,
                'subtotal' => $itemSubtotal,
                'vat_amount' => $itemVat,
                'total' => $itemTotal,
            ]);

            // Update batch quantity
            $batch->decrement('quantity', $quantityFromThisBatch);
            
            // Refresh batch to get updated quantity
            $batch->refresh();

            // Record stock movement
            StockMovement::create([
                'medicine_id' => $medicine->id,
                'batch_id' => $batch->id,
                'type' => 'sale',
                'quantity' => -$quantityFromThisBatch,
                'balance_after' => $batch->quantity,
                'reference_type' => 'App\Models\Sale',
                'reference_id' => $sale->id,
                'user_id' => $sale->user_id,
                'notes' => "Sale: {$sale->sale_number}",
            ]);

            $remainingQuantity -= $quantityFromThisBatch;
        }

        if ($remainingQuantity > 0) {
            throw new Exception("Could not fulfill complete quantity for {$medicine->name}");
        }
    }

    /**
     * Calculate sale totals including VAT.
     *
     * @param array $items
     * @param float $saleDiscount
     * @return array
     */
    protected function calculateTotals(array $items, float $saleDiscount = 0): array
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $medicine = Medicine::find($item['medicine_id']);
            $unitPrice = $item['unit_price'] ?? $medicine->selling_price;
            $itemDiscount = $item['discount'] ?? 0;
            $subtotal += ($item['quantity'] * $unitPrice) - $itemDiscount;
        }

        $subtotalAfterDiscount = $subtotal - $saleDiscount;
        $vatAmount = $subtotalAfterDiscount * self::VAT_RATE;
        $total = $subtotalAfterDiscount + $vatAmount;

        return [
            'subtotal' => round($subtotal, 2),
            'vat_amount' => round($vatAmount, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * Create payment record(s) for the sale.
     *
     * @param Sale $sale
     * @param array $data
     * @return void
     */
    protected function createPaymentRecords(Sale $sale, array $data): void
    {
        if ($data['payment_method'] === 'mixed' && isset($data['payments'])) {
            // Multiple payment methods
            foreach ($data['payments'] as $payment) {
                Payment::create([
                    'sale_id' => $sale->id,
                    'payment_method' => $payment['method'],
                    'amount' => $payment['amount'],
                    'mpesa_transaction_id' => $payment['mpesa_transaction_id'] ?? null,
                    'mpesa_phone' => $payment['mpesa_phone'] ?? null,
                    'mpesa_response' => $payment['mpesa_response'] ?? null,
                    'card_last_four' => $payment['card_last_four'] ?? null,
                    'card_type' => $payment['card_type'] ?? null,
                    'card_transaction_id' => $payment['card_transaction_id'] ?? null,
                    'reference_number' => $payment['reference_number'] ?? null,
                    'notes' => $payment['notes'] ?? null,
                ]);
            }
        } else {
            // Single payment method
            Payment::create([
                'sale_id' => $sale->id,
                'payment_method' => $data['payment_method'],
                'amount' => $sale->total,
                'mpesa_transaction_id' => $data['mpesa_transaction_id'] ?? null,
                'mpesa_phone' => $data['mpesa_phone'] ?? null,
                'mpesa_response' => $data['mpesa_response'] ?? null,
                'card_last_four' => $data['card_last_four'] ?? null,
                'card_type' => $data['card_type'] ?? null,
                'card_transaction_id' => $data['card_transaction_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
            ]);
        }
    }

    /**
     * Void a sale and restore stock.
     *
     * @param Sale $sale
     * @param int $userId
     * @param string $reason
     * @return Sale
     * @throws Exception
     */
    public function voidSale(Sale $sale, int $userId, string $reason): Sale
    {
        if (!$sale->canBeVoided()) {
            throw new Exception('This sale cannot be voided. Sales can only be voided within 24 hours of creation.');
        }

        return DB::transaction(function () use ($sale, $userId, $reason) {
            // Restore stock for each sale item
            foreach ($sale->items as $item) {
                $batch = $item->batch;
                
                // Increment batch quantity
                $batch->increment('quantity', $item->quantity);
                
                // Refresh batch to get updated quantity
                $batch->refresh();

                // Record stock movement
                StockMovement::create([
                    'medicine_id' => $item->medicine_id,
                    'batch_id' => $batch->id,
                    'type' => 'adjustment',
                    'quantity' => $item->quantity,
                    'balance_after' => $batch->quantity,
                    'reference_type' => 'App\Models\Sale',
                    'reference_id' => $sale->id,
                    'user_id' => $userId,
                    'notes' => "Voided sale: {$sale->sale_number} - {$reason}",
                ]);
            }

            // Update sale status
            $sale->update([
                'status' => 'voided',
                'void_reason' => $reason,
                'voided_by' => $userId,
                'voided_at' => now(),
            ]);

            return $sale->fresh(['items', 'payments', 'user', 'voidedByUser']);
        });
    }

    /**
     * Generate receipt data for printing.
     *
     * @param Sale $sale
     * @return array
     */
    public function generateReceipt(Sale $sale): array
    {
        return [
            'sale_number' => $sale->sale_number,
            'date' => $sale->created_at->format('Y-m-d H:i:s'),
            'cashier' => $sale->user->name,
            'customer_name' => $sale->customer_name,
            'customer_phone' => $sale->customer_phone,
            'items' => $sale->items->map(function ($item) {
                return [
                    'name' => $item->medicine_name,
                    'batch' => $item->batch_number,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount' => $item->discount,
                    'subtotal' => $item->subtotal,
                    'vat' => $item->vat_amount,
                    'total' => $item->total,
                ];
            }),
            'subtotal' => $sale->subtotal,
            'discount' => $sale->discount,
            'vat_amount' => $sale->vat_amount,
            'total' => $sale->total,
            'payment_method' => $sale->payment_method,
            'amount_tendered' => $sale->amount_tendered,
            'change_given' => $sale->change_given,
            'mpesa_transaction_id' => $sale->mpesa_transaction_id,
            'notes' => $sale->notes,
        ];
    }
}
