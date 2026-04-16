<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Need extends Model
{
    protected $fillable = [
        'lead_id',
        'place_id',
        'property_type',
        'min_area',
        'max_area',
        'area_unit',
        'min_budget',
        'max_budget',
        'description'
    ];

    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id', 'lead_id');
    }
}
