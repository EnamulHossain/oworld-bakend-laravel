<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class CouponDetail extends BaseModel
{
    use HasFactory;

    protected $table = 'coupon_details';

    protected $fillable = [
        'coupon_id',
        'coupon',
        'offer_id',
        'event_id',
        'organization_id',
        'user_id',
        'used_at',
        'is_used',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'is_used' => 'boolean',
    ];

    public function couponMaster()
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function organization()
    {
        return $this->belongsTo(User::class, 'organization_id');
    }
}
