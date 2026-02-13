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
        Schema::create('reconciliation_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('bank_balance', 15, 2); // The actual bank balance entered by user
            $table->decimal('calculated_balance', 15, 2); // System calculated balance
            $table->decimal('adjustment_amount', 15, 2); // Difference that needs adjusting
            $table->timestamp('reconciled_at');
            $table->text('notes')->nullable(); // User notes about the reconciliation
            $table->foreignId('adjustment_transaction_id')->nullable()->constrained('transactions')->onDelete('set null'); // Link to created adjustment transaction
            $table->timestamps();

            // Index for account reconciliation history
            $table->index(['account_id', 'reconciled_at']);
            $table->index(['user_id', 'reconciled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliation_entries');
    }
};