<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Attribute extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'types',
        'category_ids',
        'subcategory_ids',
        'category_id',
        'subcategory_id',
        'start_date',
        'end_date',
        'auto_expires',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'types' => 'array',
        'category_ids' => 'array',
        'subcategory_ids' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'auto_expires' => 'boolean',
    ];

    public function scopeForType($query, string $type)
    {
        return $query->where(fn ($q) => $q->whereJsonContains('types', $type)
            ->orWhere(fn ($legacy) => $legacy->whereNull('types')->where('type', $type)));
    }

    public function values()
    {
        return $this->hasMany(AttributeValue::class);
    }

    public function scopeMatchingCategory($query, int $id)
    {
        return $query->where(fn ($q) => $q->where('category_id', $id)
            ->orWhereJsonContains('category_ids', $id)
            ->orWhere(fn ($global) => $global->whereNull('category_id')
                ->where(fn ($ids) => $ids->whereNull('category_ids')->orWhereJsonLength('category_ids', 0))));
    }

    public function scopeMatchingSubcategory($query, int $id)
    {
        return $query->where(fn ($q) => $q->where('subcategory_id', $id)
            ->orWhereJsonContains('subcategory_ids', $id)
            ->orWhere(fn ($global) => $global->whereNull('subcategory_id')
                ->where(fn ($ids) => $ids->whereNull('subcategory_ids')->orWhereJsonLength('subcategory_ids', 0))));
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'attribute_category')->withTimestamps();
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory()
    {
        return $this->belongsTo(Category::class, 'subcategory_id');
    }
}
