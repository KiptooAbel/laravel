<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'medicine_id',
        'batch_number',
        'expiry_date',
        'quantity',
        'unit_cost',
        'selling_price',
        'subtotal',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    protected $appends = [
        'profit_margin',
    ];

    /**
     * Get the purchase
     */
    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * Get the medicine
     */
    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    /**
     * Get the profit margin percentage
     */
    public function getProfitMarginAttribute(): float
    {
        if ($this->unit_cost == 0) {
            return 0;
        }

        return (($this->selling_price - $this->unit_cost) / $this->unit_cost) * 100;
    }

    /**
     * Get the profit per unit
     */
    public function getProfitPerUnit(): float
    {
        return $this->selling_price - $this->unit_cost;
    }

    /**
     * Get the total profit for this item
     */
    public function getTotalProfit(): float
    {
        return $this->getProfitPerUnit() * $this->quantity;
    }
}
