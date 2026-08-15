<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationDocument extends Model
{
    protected $fillable = [
        'organization_id', 'verification_id', 'document_type', 'disk',
        'file_path', 'original_name', 'mime_type', 'file_size',
        'ocr_status', 'ocr_data',
    ];

    protected function casts(): array
    {
        return ['ocr_data' => 'array'];
    }
}
