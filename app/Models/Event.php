<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'banner',
        'thumbnail',
        'gallery_sort_order',
        'attributes',
        'status',
        'starting_date',
        'end_date',
        'location',
        'area_id',
        'address',
        'facebook_url',
        'instagram_url',
        'website_url',
        'google_map_url',
        'category_id',
        'sort_order',
        'created_by',
        'organization_id',
    ];

    protected $casts = [
        'starting_date' => 'datetime',
        'end_date' => 'datetime',
        'banner' => 'array',
        'gallery_sort_order' => 'array',
        'attributes' => 'array',
    ];

    public function getBannerAttribute($value)
    {
        $banner = $this->normalizeArrayField($value);
        $orderMap = $this->normalizeArrayField($this->attributes['gallery_sort_order'] ?? null);
        return $this->sortMediaByOrder($banner, $orderMap);
    }

    private function normalizeArrayField($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    private function sortMediaByOrder(array $media, $orderMap): array
    {
        if (!is_array($orderMap) || count($orderMap) === 0) {
            return $media;
        }

        $isList = array_keys($orderMap) === range(0, count($orderMap) - 1);
        $ranked = [];

        foreach ($media as $index => $url) {
            $order = $isList ? ($orderMap[$index] ?? null) : ($orderMap[$url] ?? null);
            $orderValue = is_numeric($order) ? (float) $order : PHP_INT_MAX;
            $ranked[] = ['url' => $url, 'order' => $orderValue, 'index' => $index];
        }

        usort($ranked, function ($a, $b) {
            if ($a['order'] === $b['order']) {
                return $a['index'] <=> $b['index'];
            }
            return $a['order'] <=> $b['order'];
        });

        return array_map(fn ($item) => $item['url'], $ranked);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function organization()
    {
        return $this->belongsTo(User::class, 'organization_id');
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
