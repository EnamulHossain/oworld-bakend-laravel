<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Offer extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'details',
        'start_date',
        'start_time',
        'end_date',
        'end_time',
        'expiration_date',
        'expiration_time',
        'address',
        'phone_number',
        'facebook_url',
        'instagram_url',
        'website_url',
        'google_map_url',
        'discount_type',
        'discount_value',
        'thumbnail',
        'cover',
        'images',
        'gallery_sort_order',
        'videos',
        'attributes',
        'category_id',
        'organization_id',
        'event_id',
        'area_id',
        'area_ids',
        'offer_type',
        'is_recurring',
        'recurring_start_date',
        'recurring_end_date',
        'recurring_days',
        'sort_order',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'expiration_date' => 'date',
        'is_recurring' => 'boolean',
        'recurring_start_date' => 'date',
        'recurring_end_date' => 'date',
        'recurring_days' => 'array',
        'images' => 'array',
        'gallery_sort_order' => 'array',
        'videos' => 'array',
        'attributes' => 'array',
        'area_ids' => 'array',
        'discount_value' => 'decimal:2',
    ];

    public function getImagesAttribute($value)
    {
        $images = $this->normalizeArrayField($value);
        $orderMap = $this->normalizeArrayField($this->attributes['gallery_sort_order'] ?? null);
        return $this->sortMediaByOrder($images, $orderMap);
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

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
