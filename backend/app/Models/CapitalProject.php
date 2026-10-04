<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapitalProject extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['target_categories' => 'array', 'baseline' => 'array', 'baseline_periods' => 'array'];
    }

    public function costLines()
    {
        return $this->belongsToMany(ExpenseLine::class, 'project_cost_lines', 'project_id', 'expense_line_id');
    }
}
