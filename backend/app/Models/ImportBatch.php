<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class ImportBatch extends Model
{
    protected $table = 'import_batches';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (ImportBatch $batch): void {
            if ($batch->isDirty('items')) {
                $batch->item_results = array_map(fn ($item) => Arr::only($item, ['period', 'status', 'statement_id', 'error']), $batch->items ?? []);
            }
        });
    }

    protected function casts(): array
    {
        return ['items' => 'array', 'item_results' => 'array'];
    }
}
