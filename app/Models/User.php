<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends BaseAuthenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $guard_name = 'sanctum';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'role',
        'parent_org_id',
        'status',
        'organization_name',
        'business_type',
        'public_subcategory',
        'public_tag',
        'is_verified',
        'categories',
        'subcategory_id',
        'subcategory_ids',
        'phone',
        'whatsapp',
        'address',
        'facebook_url',
        'instagram_url',
        'website_url',
        'google_map_url',
        'full_name',
        'first_name',
        'last_name',
        'dob',
        'gender',
        'about',
        'store_tags',
        'google_id',
        'avatar',
        'profile_banner',
        'interior_media',
        'opening_hours',
        'business_hours',
        'payment_methods',
        'facilities',
        'highlights',
        'catalog_sections',
        'catalog_items',
        'follower_count',
        'rating_average',
        'review_count',
        'referral_code',
        'referred_by_user_id',
        'signup_source',
        'signup_referrer',
        'signup_utm_campaign',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'dob' => 'date',
            'categories' => 'array',
            'subcategory_ids' => 'array',
            'is_verified' => 'boolean',
            'business_hours' => 'array',
            'payment_methods' => 'array',
            'facilities' => 'array',
            'highlights' => 'array',
            'catalog_sections' => 'array',
            'catalog_items' => 'array',
            'interior_media' => 'array',
            'store_tags' => 'array',
        ];
    }

    public function createdCategories()
    {
        return $this->hasMany(Category::class, 'created_by');
    }

    public function createdEvents()
    {
        return $this->hasMany(Event::class, 'created_by');
    }

    public function organizationEvents()
    {
        return $this->hasMany(Event::class, 'organization_id');
    }

    public function createdOffers()
    {
        return $this->hasMany(Offer::class, 'created_by');
    }

    public function organizationOffers()
    {
        return $this->hasMany(Offer::class, 'organization_id');
    }

    public function parentOrganization()
    {
        return $this->belongsTo(User::class, 'parent_org_id');
    }

    public function childOrganizations()
    {
        return $this->hasMany(User::class, 'parent_org_id');
    }

    public function organization()
    {
        return $this->hasOne(Organization::class);
    }

    public function subcategory()
    {
        return $this->belongsTo(Category::class, 'subcategory_id');
    }

    public function wishlistItems()
    {
        return $this->hasMany(WishlistItem::class);
    }

    public function referralsMade()
    {
        return $this->hasMany(Referral::class, 'referrer_user_id');
    }

    public function referralsReceived()
    {
        return $this->hasMany(Referral::class, 'referred_user_id');
    }

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referred_by_user_id');
    }
}
