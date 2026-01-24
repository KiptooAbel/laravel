<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicineBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'medicine_id',
        'batch_number',
        'quantity',
        'initial_quantity',
        'cost_price_per_unit',
        'selling_price_per_unit',
        'manufacture_date',
        'expiry_date',
        'received_date',
        'supplier_id',
        'notes',
    ];

    protected $casts = [
        'cost_price_per_unit' => 'decimal:2',
        'selling_price_per_unit' => 'decimal:2',
        'manufacture_date' => 'date',
        'expiry_date' => 'date',
        'received_date' => 'date',
    ];

    /**
     * Get the medicine this batch belongs to
     */
    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    /**
     * Get the supplier for this batch
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get stock movements for this batch
     */
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class, 'batch_id');
    }

    /**
     * Check if batch is expired
     */
    public function isExpired(): bool
    {
        return $this->expiry_date <= now();
    }

    /**
     * Check if batch is expiring soon (within 30 days)
     */
    public function isExpiringSoon(): bool
    {
        return $this->expiry_date > now() 
            && $this->expiry_date <= now()->addDays(30);
    }

    /**
     * Calculate remaining shelf life in days
     */
    public function remainingShelfLife(): int
    {
        return max(0, now()->diffInDays($this->expiry_date, false));
    }

    /**
     * Check if batch is available for sale
     */
    public function isAvailable(): bool
    {
        return $this->quantity > 0 && !$this->isExpired();
    }
}
