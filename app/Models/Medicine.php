<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Medicine extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'generic_name',
        'barcode',
        'category',
        'description',
        'manufacturer',
        'unit_price',
        'cost_price',
        'reorder_level',
        'unit_of_measure',
        'requires_prescription',
        'is_controlled',
        'is_active',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'requires_prescription' => 'boolean',
        'is_controlled' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $appends = ['total_stock'];

    /**
     * Get the batches for this medicine
     */
    public function batches()
    {
        return $this->hasMany(MedicineBatch::class);
    }

    /**
     * Get the active batches (not expired, with quantity > 0)
     */
    public function activeBatches()
    {
        return $this->hasMany(MedicineBatch::class)
            ->where('quantity', '>', 0)
            ->where('expiry_date', '>', now())
            ->orderBy('expiry_date', 'asc'); // FIFO: oldest expiry first
    }

    /**
     * Get stock movements for this medicine
     */
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Get sale items for this medicine
     */
    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Calculate total stock across all active batches
     */
    public function getTotalStockAttribute()
    {
        return $this->activeBatches()->sum('quantity');
    }

    /**
     * Check if medicine is low on stock
     */
    public function isLowStock(): bool
    {
        return $this->total_stock <= $this->reorder_level;
    }

    /**
     * Check if medicine has expired batches
     */
    public function hasExpiredBatches(): bool
    {
        return $this->batches()
            ->where('expiry_date', '<=', now())
            ->where('quantity', '>', 0)
            ->exists();
    }

    /**
     * Get batches expiring soon (within 30 days)
     */
    public function expiringSoonBatches()
    {
        return $this->batches()
            ->where('quantity', '>', 0)
            ->where('expiry_date', '>', now())
            ->where('expiry_date', '<=', now()->addDays(30))
            ->orderBy('expiry_date', 'asc');
    }
}
