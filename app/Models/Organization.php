<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'parent_organization_id',
        'category_id',
        'subcategory_id',
        'subcategory_ids',
        'name',
        'business_type',
        'email',
        'phone',
        'whatsapp',
        'address',
        'about',
        'facebook_url',
        'instagram_url',
        'website_url',
        'google_map_url',
        'logo',
        'profile_banner',
        'status',
        'verification_status',
        'is_verified',
        'follower_count',
        'review_count',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'subcategory_ids' => 'array',
        ];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parentOrganization()
    {
        return $this->belongsTo(self::class, 'parent_organization_id');
    }

    public function branches()
    {
        return $this->hasMany(self::class, 'parent_organization_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory()
    {
        return $this->belongsTo(Category::class, 'subcategory_id');
    }

    public function verification()
    {
        return $this->hasOne(OrganizationVerification::class);
    }

    public function documents()
    {
        return $this->hasMany(OrganizationDocument::class);
    }
}
