<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseLine extends Model
{
    protected $table = 'expense_lines';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}
