<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StatusHistory extends Model
{
    use SoftDeletes;

    protected $table = 'status_history';

    protected $primaryKey = 'history_id';

    public $timestamps = false;

    protected $fillable = [
        'lead_id',
        'status_id',
        'status_type',
        'remark',
        'reschedule_time', // 🔥 add
        'shift',           // 🔥 add
        'added_by',     // 🔥 NEW
        'updated_at'
    ];
    // 🔥 ADD THIS HERE
    protected $appends = ['added_by_name'];

    public function getAddedByNameAttribute()
    {
        // check sales
        $sales = \App\Models\SalesTeam::where('sales_person_id', $this->added_by)->first();
        if ($sales) {
            return $sales->name;
        }

        // fallback → admin
        return 'Admin';
    }

    // ✅ Lead relation
    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    // 🔥 Who added status
    public function addedBy()
    {
        return $this->belongsTo(SalesTeam::class, 'added_by', 'sales_person_id');
        // 👉 change to SalesTeam::class if you use that table
    }
}
