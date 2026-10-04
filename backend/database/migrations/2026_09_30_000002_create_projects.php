<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capital_projects', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('description')->nullable();
            $t->date('completion_date')->nullable();
            $t->json('target_categories');
            $t->json('baseline');
            $t->json('baseline_periods');
            $t->text('baseline_notes')->nullable();
            $t->timestamps();
        });
        Schema::create('project_cost_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained('capital_projects')->cascadeOnDelete();
            $t->foreignId('expense_line_id')->unique()->constrained('expense_lines')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_cost_lines');
        Schema::dropIfExists('capital_projects');
    }
};
