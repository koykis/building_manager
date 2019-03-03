<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ExpenseType extends Model
{

    public $guarded = [];

    public function expenses()
    {
        $this->belongsToMany('App\Expense');
    }

}
