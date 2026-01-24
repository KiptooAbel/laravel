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
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('generic_name')->nullable();
            $table->string('barcode')->unique()->nullable();
            $table->string('category')->nullable(); // Tablet, Syrup, Injection, etc.
            $table->text('description')->nullable();
            $table->string('manufacturer')->nullable();
            $table->decimal('unit_price', 10, 2); // Selling price per unit
            $table->decimal('cost_price', 10, 2); // Purchase price per unit
            $table->integer('reorder_level')->default(10); // Minimum stock level
            $table->string('unit_of_measure')->default('piece'); // piece, bottle, box, etc.
            $table->boolean('requires_prescription')->default(false);
            $table->boolean('is_controlled')->default(false); // For controlled substances
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes(); // For soft deletion

            // Indexes for faster searches
            $table->index('name');
            $table->index('generic_name');
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medicines');
    }
};
