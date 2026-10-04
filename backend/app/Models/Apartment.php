<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Apartment extends Model
{
    protected $table = 'apartments';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'car_lift' => 'boolean', 'area' => 'decimal:2'];
    }
}
