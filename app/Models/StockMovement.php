<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'medicine_id',
        'batch_id',
        'type',
        'quantity',
        'balance_after',
        'unit_price',
        'reference_type',
        'reference_id',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
    ];

    /**
     * Get the medicine for this movement
     */
    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    /**
     * Get the batch for this movement
     */
    public function batch()
    {
        return $this->belongsTo(MedicineBatch::class, 'batch_id');
    }

    /**
     * Get the user who made this movement
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the related reference (polymorphic-like)
     */
    public function reference()
    {
        if ($this->reference_type && $this->reference_id) {
            $modelClass = "App\\Models\\" . $this->reference_type;
            if (class_exists($modelClass)) {
                return $modelClass::find($this->reference_id);
            }
        }
        return null;
    }

    /**
     * Scope for additions (positive quantity)
     */
    public function scopeAdditions($query)
    {
        return $query->where('quantity', '>', 0);
    }

    /**
     * Scope for deductions (negative quantity)
     */
    public function scopeDeductions($query)
    {
        return $query->where('quantity', '<', 0);
    }

    /**
     * Scope by movement type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }
}
