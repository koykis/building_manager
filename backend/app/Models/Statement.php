<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Statement extends Model
{
    protected $table = 'statements';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['reviewed' => 'boolean', 'printed_total' => 'decimal:2', 'exceptions' => 'array'];
    }

    public function sections()
    {
        return $this->hasMany(StatementSection::class);
    }

    public function lines()
    {
        return $this->hasMany(ExpenseLine::class);
    }

    public function rows()
    {
        return $this->hasMany(ApartmentStatementRow::class);
    }

    public function documents()
    {
        return $this->hasMany(SourceDocument::class);
    }
}
