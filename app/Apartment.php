<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Apartment extends Model
{

    public $guarded = [];

    public function user()
    {
        $this->belongsTo('App\User');
    }

    public function building()
    {
        $this->belongsTo('App\Building');
    }

    public function expenses()
    {
        $this->hasMany('App\Expenses');
    }

}
