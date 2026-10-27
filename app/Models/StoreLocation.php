<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreLocation extends Model
{
    protected $fillable = ['organization_id', 'name', 'address', 'area_id', 'phone', 'whatsapp', 'google_map_url', 'opening_hours', 'status'];
}
