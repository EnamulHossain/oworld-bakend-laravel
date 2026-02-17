<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Coupon extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'organization_id',
        'status',
        'start_date',
        'start_time',
        'end_date',
        'end_time',
        'total_coupon',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_coupon' => 'integer',
    ];

    public function organization()
    {
        return $this->belongsTo(User::class, 'organization_id');
    }

    public function details()
    {
        return $this->hasMany(CouponDetail::class, 'coupon_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
