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
        Schema::create('medicine_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->onDelete('cascade');
            $table->string('batch_number');
            $table->integer('quantity'); // Current quantity in this batch
            $table->integer('initial_quantity'); // Original quantity when added
            $table->decimal('cost_price_per_unit', 10, 2); // Cost at time of purchase
            $table->decimal('selling_price_per_unit', 10, 2); // Selling price for this batch
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date');
            $table->date('received_date');
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('expiry_date');
            $table->index('batch_number');
            $table->index(['medicine_id', 'expiry_date']); // For FIFO queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medicine_batches');
    }
};
