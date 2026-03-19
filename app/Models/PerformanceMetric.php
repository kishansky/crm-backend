<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PerformanceMetric extends Model
{
    use SoftDeletes;

    protected $table = 'performance_metrics';
    protected $primaryKey = 'perf_id';

    protected $fillable = [
        'sales_person_id',
        'report_date',
        'total_leads',
        'total_attended',
        'total_calls',
        'closed_ordered'
    ];

    public function salesPerson()
    {
        return $this->belongsTo(SalesTeam::class, 'sales_person_id');
    }
}