<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrganizationBranch extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'address',
        'phone',
        'google_map_url',
        'opening_hours',
        'status',
        'delivery_available',
        'sort_order',
    ];

    protected $casts = [
        'delivery_available' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function organization()
    {
        return $this->belongsTo(User::class, 'organization_id');
    }

    public function offers()
    {
        return $this->belongsToMany(Offer::class, 'offer_branch', 'organization_branch_id', 'offer_id')
            ->withTimestamps();
    }
}
