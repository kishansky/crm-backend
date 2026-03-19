<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StatusHistory extends Model
{
    use SoftDeletes;

    protected $table = 'status_history'; // 🔥 IMPORTANT

    protected $primaryKey = 'history_id';

    public $timestamps = false; // because you only have updated_at

    protected $fillable = [
        'lead_id',
        'status_type',
        'remark',
        'updated_at'
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
}