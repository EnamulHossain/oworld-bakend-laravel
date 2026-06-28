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
        'start_date',
        'start_time',
        'end_date',
        'end_time',
        'expiration_date',
        'expiration_time',
        'sort_order',
    ];

    protected $casts = [
        'target_id' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'expiration_date' => 'date',
        'sort_order' => 'integer',
    ];

    public function block()
    {
        return $this->belongsTo(ContentBlock::class, 'content_block_id');
    }
}
