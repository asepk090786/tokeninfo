<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CbtInfo extends Model
{
    protected $fillable = [
        'token',
        'cbt_url',
        'description',
    ];
}
