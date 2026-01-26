<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'short_name',
        'image',
        'icon',
        'order',
        'status',
        'description',
        'created_by',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
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
