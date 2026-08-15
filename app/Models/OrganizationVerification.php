<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationVerification extends Model
{
    protected $fillable = [
        'organization_id', 'owner_full_name', 'owner_phone', 'owner_email',
        'nid_no', 'trade_license_no', 'trade_license_valid_until',
        'organization_valid_until', 'status', 'reviewed_by', 'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'trade_license_valid_until' => 'date',
            'organization_valid_until' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }
}
