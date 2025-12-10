<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WishlistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'item_type',
        'item_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'item_id');
    }

    public function offer()
    {
        return $this->belongsTo(Offer::class, 'item_id');
    }
}
