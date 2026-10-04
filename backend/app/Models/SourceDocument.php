<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourceDocument extends Model
{
    protected $table = 'source_documents';

    protected $guarded = [];

    protected function casts(): array
    {
        return [];
    }
}
