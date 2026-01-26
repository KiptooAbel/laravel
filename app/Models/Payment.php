<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'payment_method',
        'amount',
        'mpesa_transaction_id',
        'mpesa_phone',
        'mpesa_response',
        'card_last_four',
        'card_type',
        'card_transaction_id',
        'reference_number',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'mpesa_response' => 'array',
    ];

    /**
     * Get the sale that owns the payment.
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
