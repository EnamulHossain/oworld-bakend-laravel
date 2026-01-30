<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HighlightReel extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'thumbnail',
        'galleries',
        'external_link',
        'offer_id',
        'event_id',
        'sort_order',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'galleries' => 'array',
        'is_active' => 'boolean',
    ];

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

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
}
