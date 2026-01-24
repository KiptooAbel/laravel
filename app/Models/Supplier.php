<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'contact_person',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the batches supplied by this supplier
     */
    public function batches()
    {
        return $this->hasMany(MedicineBatch::class);
    }

    /**
     * Get the purchases from this supplier
     */
    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }
}
