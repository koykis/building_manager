<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Building extends Model
{

    public $guarded = [];

    public function user()
    {
        $this->belongsTo('App\User');
    }

    public function apartments()
    {
        $this->hasMany('App\Apartment');
    }

}
