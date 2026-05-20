<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Attribute extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'category_id',
        'start_date',
        'end_date',
        'auto_expires',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'auto_expires' => 'boolean',
    ];

    public function values()
    {
        return $this->hasMany(AttributeValue::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'attribute_category')->withTimestamps();
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
