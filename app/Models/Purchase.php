<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'purchase_number',
        'supplier_id',
        'user_id',
        'purchase_date',
        'subtotal',
        'tax',
        'discount',
        'total',
        'payment_status',
        'paid_amount',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    protected $appends = [
        'balance',
    ];

    /**
     * Generate unique purchase number
     */
    public static function generatePurchaseNumber(): string
    {
        $date = now()->format('Ymd');
        $lastPurchase = self::whereDate('created_at', now())
            ->latest('id')
            ->first();

        $sequence = $lastPurchase ? (intval(substr($lastPurchase->purchase_number, -4)) + 1) : 1;

        return 'PUR-' . $date . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get the supplier
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the user who recorded this purchase
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the purchase items
     */
    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * Get the purchase items (alias)
     */
    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * Get the outstanding balance
     */
    public function getBalanceAttribute(): float
    {
        return $this->total - $this->paid_amount;
    }

    /**
     * Check if purchase is fully paid
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Check if purchase is partially paid
     */
    public function isPartiallyPaid(): bool
    {
        return $this->payment_status === 'partial';
    }

    /**
     * Check if purchase is pending payment
     */
    public function isPending(): bool
    {
        return $this->payment_status === 'pending';
    }

    /**
     * Record a payment
     */
    public function recordPayment(float $amount): void
    {
        $this->paid_amount += $amount;

        if ($this->paid_amount >= $this->total) {
            $this->payment_status = 'paid';
        } elseif ($this->paid_amount > 0) {
            $this->payment_status = 'partial';
        }

        $this->save();
    }
}
