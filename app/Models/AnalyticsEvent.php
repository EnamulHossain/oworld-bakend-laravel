<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class AnalyticsEvent extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'event_name',
        'page',
        'action',
        'filter',
        'channel',
        'highlight_id',
        'offer_id',
        'event_id',
        'organization_id',
        'user_id',
        'client_session_id',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
