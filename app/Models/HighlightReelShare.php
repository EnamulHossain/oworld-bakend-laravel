<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class HighlightReelShare extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'highlight_reel_id',
        'offer_id',
        'event_id',
        'organization_id',
        'user_id',
        'channel',
    ];

    public function highlight()
    {
        return $this->belongsTo(HighlightReel::class, 'highlight_reel_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
