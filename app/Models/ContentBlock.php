<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContentBlock extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'status',
        'start_date',
        'start_time',
        'end_date',
        'end_time',
        'expiration_date',
        'expiration_time',
        'is_active',
        'is_featured',
        'teared_block',
        'thumbnail_image',
        'featured_sort_order',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'expiration_date' => 'date',
        'is_featured' => 'boolean',
        'teared_block' => 'boolean',
        'featured_sort_order' => 'integer',
        'sort_order' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(ContentBlockItem::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
