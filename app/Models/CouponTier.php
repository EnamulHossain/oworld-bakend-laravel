<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class CouponTier extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'coupon_id',
        'label',
        'quantity',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'min_order_amount',
        'referral_required_count',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'discount_value' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'referral_required_count' => 'integer',
        'sort_order' => 'integer',
    ];

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function details()
    {
        return $this->hasMany(CouponDetail::class, 'coupon_tier_id');
    }
}
