<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{

    public $guarded = [];

    public function type()
    {
        $this->hasOne('App\ExpenseType', 'type_id');
    }

}
