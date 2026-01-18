<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'banner',
        'status',
        'starting_date',
        'end_date',
        'location',
        'area_id',
        'address',
        'category_id',
        'filter_type_id',
        'created_by',
        'organization_id',
    ];

    protected $casts = [
        'starting_date' => 'datetime',
        'end_date' => 'datetime',
        'banner' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function organization()
    {
        return $this->belongsTo(User::class, 'organization_id');
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function filterType()
    {
        return $this->belongsTo(FilterType::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
