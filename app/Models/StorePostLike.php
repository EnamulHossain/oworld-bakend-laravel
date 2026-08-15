<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorePostLike extends Model
{
    protected $fillable = ['store_post_id', 'user_id'];
}
