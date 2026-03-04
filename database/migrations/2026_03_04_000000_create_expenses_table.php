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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_type'); // rent, electricity, transport, salaries, maintenance, etc.
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->date('expense_date');
            $table->string('payment_method')->nullable(); // cash, bank_transfer, mpesa, etc.
            $table->string('reference_number')->nullable(); // receipt/invoice number
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users'); // Who recorded the expense
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('expense_type');
            $table->index('expense_date');
            $table->index('recorded_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
