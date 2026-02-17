<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Attribute extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'sort_order',
    ];

    public function values()
    {
        return $this->hasMany(AttributeValue::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'attribute_category')->withTimestamps();
    }
}
