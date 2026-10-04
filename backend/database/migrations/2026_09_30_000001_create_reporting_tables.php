<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apartments', function (Blueprint $t) {
            $t->id();
            $t->string('label')->unique();
            $t->string('alias')->unique();
            $t->unsignedTinyInteger('floor');
            $t->decimal('area', 8, 2)->nullable();
            $t->boolean('car_lift')->default(false);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('resident');
            $t->foreignId('apartment_id')->nullable()->constrained()->restrictOnDelete();
            $t->boolean('active')->default(false);
            $t->string('locale')->default('el');
        });
        Schema::create('expense_categories', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name_el');
            $t->string('name_en');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('statements', function (Blueprint $t) {
            $t->id();
            $t->string('period', 7)->index();
            $t->date('issue_date')->nullable();
            $t->unsignedInteger('revision')->default(1);
            $t->unsignedInteger('version')->default(1);
            $t->string('status')->default('draft');
            $t->string('published_period', 7)->nullable()->unique();
            $t->decimal('printed_total', 14, 2);
            $t->json('exceptions')->nullable();
            $t->text('notes')->nullable();
            $t->boolean('reviewed')->default(false);
            $t->timestamps();
            $t->unique(['period', 'revision']);
        });
        Schema::create('source_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('statement_id')->constrained()->restrictOnDelete();
            $t->string('filename');
            $t->string('path');
            $t->string('sha256', 64);
            $t->string('mime');
            $t->timestamps();
            $t->unique(['statement_id', 'sha256']);
        });
        Schema::create('statement_sections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('statement_id')->constrained()->cascadeOnDelete();
            $t->string('key');
            $t->string('label');
            $t->string('allocation_column');
            $t->decimal('printed_total', 14, 2);
            $t->timestamps();
            $t->unique(['statement_id', 'key']);
        });
        Schema::create('expense_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('statement_id')->constrained()->cascadeOnDelete();
            $t->string('section_key');
            $t->foreignId('category_id')->constrained('expense_categories')->restrictOnDelete();
            $t->string('description');
            $t->decimal('amount', 14, 2);
            $t->string('classification')->default('operating');
            $t->timestamps();
        });
        Schema::create('apartment_statement_rows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('statement_id')->constrained()->cascadeOnDelete();
            $t->foreignId('apartment_id')->constrained()->restrictOnDelete();
            $t->decimal('printed_total', 14, 2);
            $t->json('metrics');
            $t->timestamps();
            $t->unique(['statement_id', 'apartment_id']);
        });
        Schema::create('allocation_cells', function (Blueprint $t) {
            $t->id();
            $t->foreignId('row_id')->constrained('apartment_statement_rows')->cascadeOnDelete();
            $t->string('column');
            $t->string('raw')->nullable();
            $t->decimal('amount', 14, 4)->nullable();
            $t->string('state');
            $t->string('provenance')->default('printed');
            $t->timestamps();
            $t->unique(['row_id', 'column']);
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('action');
            $t->string('entity');
            $t->unsignedBigInteger('entity_id');
            $t->json('changes')->nullable();
            $t->timestamps();
        });
        Schema::create('import_batches', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->json('items');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['import_batches', 'audit_events', 'allocation_cells', 'apartment_statement_rows', 'expense_lines', 'statement_sections', 'source_documents', 'statements', 'expense_categories'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('apartment_id');
            $t->dropColumn(['role', 'active', 'locale']);
        });
        Schema::dropIfExists('apartments');
    }
};
