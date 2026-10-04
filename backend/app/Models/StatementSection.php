<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatementSection extends Model
{
    protected $table = 'statement_sections';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['printed_total' => 'decimal:2'];
    }
}
