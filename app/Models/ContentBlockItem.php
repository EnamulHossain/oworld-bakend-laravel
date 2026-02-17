<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContentBlockItem extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'content_block_id',
        'type',
        'target_id',
        'title',
        'subtitle',
        'image',
        'external_link',
        'sort_order',
    ];

    protected $casts = [
        'target_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function block()
    {
        return $this->belongsTo(ContentBlock::class, 'content_block_id');
    }
}
