<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class HighlightReel extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'title',
        'thumbnail',
        'external_link',
        'sort_order',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function reactions()
    {
        return $this->hasMany(HighlightReelReaction::class, 'highlight_reel_id');
    }

    public function shares()
    {
        return $this->hasMany(HighlightReelShare::class, 'highlight_reel_id');
    }

    public function items()
    {
        return $this->hasMany(HighlightReelItem::class, 'highlight_id');
    }
}
