<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Facility extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'image',
        'order',
        'created_by',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
