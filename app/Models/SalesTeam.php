<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesTeam extends Model
{
    use SoftDeletes;

    protected $table = 'sales_team';
    protected $primaryKey = 'sales_person_id';
    public $incrementing = false;

    protected $fillable = [
        'sales_person_id',
        'name',
        'email',
        'is_active'
    ];

    public function leads()
    {
        return $this->hasMany(Lead::class,'assigned_to');
    }

    public function performance()
    {
        return $this->hasMany(PerformanceMetric::class,'sales_person_id');
    }
}
