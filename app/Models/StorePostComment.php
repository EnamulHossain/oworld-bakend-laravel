<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorePostComment extends Model
{
    protected $fillable = ['store_post_id', 'user_id', 'body'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
