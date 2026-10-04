<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AllocationCell extends Model
{
    protected $table = 'allocation_cells';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'decimal:4'];
    }
}
