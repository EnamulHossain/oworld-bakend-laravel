<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HighlightReelItem extends Model
{
    use HasFactory;

    protected $table = 'highlight_reels_items';

    protected $fillable = [
        'highlight_id',
        'title',
        'subtitle',
        'description',
        'offer_id',
        'event_id',
        'organization_id',
        'image',
        'external_link',
        'sort_order',
        'is_active',
        'created_by',
        'updated_by',
    ];

    public function highlight()
    {
        return $this->belongsTo(HighlightReel::class, 'highlight_id');
    }

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function organization()
    {
        return $this->belongsTo(User::class, 'organization_id');
    }
}
