<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'details',
        'start_date',
        'end_date',
        'address',
        'facebook_url',
        'instagram_url',
        'website_url',
        'google_map_url',
        'discount_type',
        'discount_value',
        'thumbnail',
        'cover',
        'images',
        'videos',
        'attributes',
        'category_id',
        'organization_id',
        'event_id',
        'area_id',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'images' => 'array',
        'videos' => 'array',
        'attributes' => 'array',
        'discount_value' => 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function organization()
    {
        return $this->belongsTo(User::class, 'organization_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
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
