<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
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
