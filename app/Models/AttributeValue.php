<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class AttributeValue extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'attribute_id',
        'value',
        'color_code',
    ];

    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }
}
