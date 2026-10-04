<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApartmentStatementRow extends Model
{
    protected $table = 'apartment_statement_rows';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['printed_total' => 'decimal:2', 'metrics' => 'array'];
    }

    public function cells()
    {
        return $this->hasMany(AllocationCell::class, 'row_id');
    }
}
