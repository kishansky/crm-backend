<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

class SalesTeam extends Model
{
    use HasApiTokens, SoftDeletes;

    protected $table = 'sales_team';
    protected $primaryKey = 'sales_person_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'sales_person_id',
        'name',
        'email',
        'password', // ✅ added
        'is_active'
    ];

    // ✅ Hide password in API responses
    protected $hidden = [
        'password',
    ];

    // ✅ Automatically hash password when set
    public function setPasswordAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['password'] = Hash::make($value);
        }
    }

    public function leads()
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }

    public function performance()
    {
        return $this->hasMany(PerformanceMetric::class, 'sales_person_id');
    }
}