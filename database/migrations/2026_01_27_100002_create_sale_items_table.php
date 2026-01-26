<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->onDelete('cascade');
            $table->foreignId('medicine_id')->constrained()->onDelete('cascade');
            $table->foreignId('batch_id')->constrained('medicine_batches')->onDelete('cascade');
            
            $table->string('medicine_name'); // Store name for historical records
            $table->string('batch_number'); // Store batch number for historical records
            
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2); // Price at time of sale
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2); // quantity * unit_price
            $table->decimal('vat_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2); // (subtotal - discount) + vat_amount
            
            $table->timestamps();
            
            // Indexes
            $table->index('sale_id');
            $table->index('medicine_id');
            $table->index('batch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
