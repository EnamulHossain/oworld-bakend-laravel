<?php

namespace App\Models;

class StorePost extends BaseModel
{
    protected $fillable = [
        'organization_id', 'type', 'title', 'description', 'image', 'media', 'is_pinned', 'pin_order',
    ];

    protected function casts(): array
    {
        return ['media' => 'array', 'is_pinned' => 'boolean', 'pin_order' => 'integer'];
    }

    public function organization()
    {
        return $this->belongsTo(User::class, 'organization_id');
    }
}
