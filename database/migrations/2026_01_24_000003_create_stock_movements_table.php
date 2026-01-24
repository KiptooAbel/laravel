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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->onDelete('cascade');
            $table->foreignId('batch_id')->nullable()->constrained('medicine_batches')->nullOnDelete();
            $table->enum('type', ['purchase', 'sale', 'adjustment', 'return', 'expiry', 'damage']);
            $table->integer('quantity'); // Positive for additions, negative for deductions
            $table->integer('balance_after'); // Stock level after this movement
            $table->decimal('unit_price', 10, 2)->nullable(); // Price at time of movement
            $table->string('reference_type')->nullable(); // Sale, Purchase, etc.
            $table->unsignedBigInteger('reference_id')->nullable(); // ID of related record
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Who made the movement
            $table->timestamps();

            // Indexes
            $table->index('medicine_id');
            $table->index('type');
            $table->index(['reference_type', 'reference_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
