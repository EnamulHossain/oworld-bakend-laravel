<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'short_name',
        'image',
        'banner',
        'gallery_sort_order',
        'icon',
        'order',
        'status',
        'description',
        'parent_id',
        'created_by',
    ];

    protected $casts = [
        'order' => 'integer',
        'banner' => 'array',
        'gallery_sort_order' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order')->orderBy('name');
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function offers()
    {
        return $this->hasMany(Offer::class);
    }

    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, 'attribute_category')->withTimestamps();
    }
}
