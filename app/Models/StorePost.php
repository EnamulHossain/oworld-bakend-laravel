<?php

namespace App\Models;

class StorePost extends BaseModel
{
    protected $fillable = [
        'organization_id', 'type', 'source_id', 'title', 'description', 'image', 'media', 'is_pinned', 'pin_order',
    ];

    protected function casts(): array
    {
        return ['media' => 'array', 'source_id' => 'integer', 'is_pinned' => 'boolean', 'pin_order' => 'integer'];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function likes() { return $this->hasMany(StorePostLike::class); }
    public function comments() { return $this->hasMany(StorePostComment::class); }
}
