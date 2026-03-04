<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'expense_type',
        'description',
        'amount',
        'expense_date',
        'payment_method',
        'reference_number',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
    ];

    /**
     * Get the user who recorded the expense
     */
    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Common expense types
     */
    public static function expenseTypes()
    {
        return [
            'rent' => 'Rent',
            'electricity' => 'Electricity',
            'water' => 'Water',
            'transport' => 'Transport',
            'salaries' => 'Salaries',
            'maintenance' => 'Maintenance',
            'marketing' => 'Marketing',
            'office_supplies' => 'Office Supplies',
            'communication' => 'Communication (Phone/Internet)',
            'insurance' => 'Insurance',
            'taxes' => 'Taxes & Licenses',
            'other' => 'Other',
        ];
    }
}
