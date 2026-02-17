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
        Schema::table('transactions', function (Blueprint $table) {
            // Index for dashboard queries (user_id + date for sorting and filtering)
            $table->index(['user_id', 'date', 'created_at'], 'transactions_user_date_created_index');
            
            // Index for account balance calculations (account_id for grouping)
            $table->index(['account_id', 'user_id'], 'transactions_account_user_index');
            
            // Index for monthly summaries (user_id + date for time-based aggregations)
            $table->index(['user_id', 'date'], 'transactions_user_date_index');
            
            // Index for category-based queries
            $table->index(['user_id', 'category_id', 'date'], 'transactions_user_category_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_user_date_created_index');
            $table->dropIndex('transactions_account_user_index');
            $table->dropIndex('transactions_user_date_index');
            $table->dropIndex('transactions_user_category_date_index');
        });
    }
};