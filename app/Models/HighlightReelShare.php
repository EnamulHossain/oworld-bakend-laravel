<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HighlightReelShare extends Model
{
    use HasFactory;

    protected $fillable = [
        'highlight_reel_id',
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
