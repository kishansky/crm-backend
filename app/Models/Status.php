<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    protected $fillable = ['name', 'color', 'is_active','orders'];

    public function histories()
    {
        return $this->hasMany(StatusHistory::class, 'status_id');
    }
}
