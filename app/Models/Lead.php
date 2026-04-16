<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use SoftDeletes;

    protected $table = 'leads_master';
    protected $primaryKey = 'lead_id';
    public $incrementing = false;

    protected $fillable = [
        'lead_id',
        'timestamp',
        'source',
        'company_name',
        'contact_person',
        'phone_number',
        'email',
        'enquiry_description',
        'assigned_to',
        'is_form'
    ];

    public function salesPerson()
    {
        return $this->belongsTo(SalesTeam::class, 'assigned_to');
    }

    public function statusHistory()
    {
        return $this->hasMany(StatusHistory::class, 'lead_id');
    }

    public function latestStatus()
    {
        return $this->hasOne(StatusHistory::class, 'lead_id', 'lead_id')
            ->latestOfMany('history_id');
    }

    public function needs()
    {
        return $this->hasMany(Need::class, 'lead_id', 'lead_id');
    }
}
