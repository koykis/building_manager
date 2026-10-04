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
        Schema::table('statements', function (Blueprint $table) {
            $table->index(['status', 'period'], 'statements_status_period_index');
        });
        Schema::table('expense_lines', function (Blueprint $table) {
            $table->index(['statement_id', 'classification', 'category_id', 'amount'], 'expense_lines_reporting_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_lines', function (Blueprint $table) {
            $table->dropIndex('expense_lines_reporting_index');
        });
        Schema::table('statements', function (Blueprint $table) {
            $table->dropIndex('statements_status_period_index');
        });
    }
};
