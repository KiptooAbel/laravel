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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->onDelete('cascade');
            
            $table->enum('payment_method', ['cash', 'mpesa', 'card'])->default('cash');
            $table->decimal('amount', 10, 2);
            
            // For M-Pesa
            $table->string('mpesa_transaction_id')->nullable();
            $table->string('mpesa_phone')->nullable();
            $table->json('mpesa_response')->nullable();
            
            // For Card
            $table->string('card_last_four')->nullable();
            $table->string('card_type')->nullable();
            $table->string('card_transaction_id')->nullable();
            
            // General
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('sale_id');
            $table->index('payment_method');
            $table->index('mpesa_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
