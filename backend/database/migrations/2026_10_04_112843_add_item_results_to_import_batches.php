<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->json('item_results')->nullable();
        });
        DB::table('import_batches')->select(['id', 'items'])->chunkById(1, function ($batches) {
            foreach ($batches as $batch) {
                $items = json_decode($batch->items, true, 512, JSON_THROW_ON_ERROR);
                $results = array_map(fn ($item) => Arr::only($item, ['period', 'status', 'statement_id', 'error']), $items);
                DB::table('import_batches')->where('id', $batch->id)->update(['item_results' => json_encode($results, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropColumn('item_results');
        });
    }
};
