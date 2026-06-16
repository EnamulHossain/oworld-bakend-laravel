<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Coupon extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'image',
        'description',
        'modal_image',
        'modal_title',
        'modal_main_text',
        'modal_sub_text',
        'modal_placeholder_text',
        'modal_success_message',
        'campaign_type',
        'organization_id',
        'status',
        'start_date',
        'start_time',
        'end_date',
        'end_time',
        'expiration_date',
        'expiration_time',
        'total_coupon',
        'usage_limit_per_user',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'expiration_date' => 'date',
        'total_coupon' => 'integer',
        'usage_limit_per_user' => 'integer',
    ];

    public function organization()
    {
        return $this->belongsTo(User::class, 'organization_id');
    }

    public function details()
    {
        return $this->hasMany(CouponDetail::class, 'coupon_id');
    }

    public function tiers()
    {
        return $this->hasMany(CouponTier::class, 'coupon_id')->orderBy('sort_order')->orderBy('id');
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
