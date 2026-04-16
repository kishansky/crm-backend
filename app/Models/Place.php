<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Place extends Model
{
    protected $fillable = [
        'name',
        'state',
        'country',
        'is_active'
    ];

    public function needs()
    {
        return $this->hasMany(Need::class);
    }
}
