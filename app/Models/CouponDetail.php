<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class CouponDetail extends BaseModel
{
    use HasFactory;

    protected $table = 'coupon_details';

    protected $fillable = [
        'coupon_id',
        'coupon_tier_id',
        'coupon',
        'offer_id',
        'event_id',
        'organization_id',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'min_order_amount',
        'referral_required_count',
        'user_id',
        'claimed_by_user_id',
        'claimed_at',
        'used_at',
        'is_used',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'referral_required_count' => 'integer',
        'claimed_at' => 'datetime',
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

    public function tier()
    {
        return $this->belongsTo(CouponTier::class, 'coupon_tier_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function organization()
    {
        return $this->belongsTo(User::class, 'organization_id');
    }

    public function claimedBy()
    {
        return $this->belongsTo(User::class, 'claimed_by_user_id');
    }
}
