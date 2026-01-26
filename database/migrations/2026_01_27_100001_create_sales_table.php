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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number')->unique(); // e.g., SALE-2026-0001
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Cashier/Pharmacist
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            
            // Amounts
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('vat_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            
            // Payment
            $table->enum('payment_method', ['cash', 'mpesa', 'card', 'mixed'])->default('cash');
            $table->decimal('amount_tendered', 10, 2)->nullable();
            $table->decimal('change_given', 10, 2)->nullable();
            $table->string('mpesa_transaction_id')->nullable();
            
            // Status
            $table->enum('status', ['completed', 'voided', 'pending'])->default('completed');
            $table->text('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('voided_at')->nullable();
            
            // Notes
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('sale_number');
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
