<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Category;
use App\Models\Event;
use App\Models\Offer;

class SampleDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $orgId = 2;  // org@example.com seeded earlier
        $adminId = 1; // admin@example.com seeded earlier

        $categories = [
            [
                'name' => 'Music',
                'short_name' => 'Music',
                'icon' => '🎵',
                'order' => 1,
                'status' => 'active',
                'description' => 'Concerts, festivals, and live gigs.',
            ],
            [
                'name' => 'Tech',
                'short_name' => 'Tech',
                'icon' => '💻',
                'order' => 2,
                'status' => 'active',
                'description' => 'Meetups, hackathons, and conferences.',
            ],
            [
                'name' => 'Food',
                'short_name' => 'Food',
                'icon' => '🍽️',
                'order' => 3,
                'status' => 'active',
                'description' => 'Taste events, pop-ups, and restaurant weeks.',
            ],
        ];

        $categoryIds = [];
        foreach ($categories as $cat) {
            $record = Category::updateOrCreate(
                ['name' => $cat['name']],
                $cat + ['created_by' => $adminId]
            );
            $categoryIds[$cat['name']] = $record->id;
        }

        $now = Carbon::now();
        $events = [
            [
                'name' => 'Summer Beats Festival',
                'description' => 'Outdoor music festival with multiple stages.',
                'banner' => ['/uploads/events/sample-music.jpg'],
                'status' => 'published',
                'starting_date' => $now->copy()->addDays(7),
                'end_date' => $now->copy()->addDays(8),
                'location' => 'Central Park',
                'category_id' => $categoryIds['Music'] ?? null,
            ],
            [
                'name' => 'Tech Innovators Meetup',
                'description' => 'Talks and networking for developers and founders.',
                'banner' => ['/uploads/events/sample-tech.jpg'],
                'status' => 'published',
                'starting_date' => $now->copy()->addDays(14),
                'end_date' => $now->copy()->addDays(14)->addHours(3),
                'location' => 'Downtown Hub',
                'category_id' => $categoryIds['Tech'] ?? null,
            ],
        ];

        $eventIds = [];
        foreach ($events as $evt) {
            $record = Event::updateOrCreate(
                ['name' => $evt['name']],
                $evt + [
                    'created_by' => $adminId,
                    'organization_id' => $orgId,
                ]
            );
            $eventIds[$evt['name']] = $record->id;
        }

        $offers = [
            [
                'name' => 'Festival Early Bird',
                'details' => '20% off early bird tickets for Summer Beats.',
                'start_date' => $now->copy()->addDays(1),
                'end_date' => $now->copy()->addDays(5),
                'discount_type' => 'percentage',
                'discount_value' => 20,
                'thumbnail' => null,
                'cover' => '/uploads/offers/sample-festival.jpg',
                'images' => ['/uploads/offers/sample-festival.jpg'],
                'videos' => [],
                'category_id' => $categoryIds['Music'] ?? null,
                'organization_id' => $orgId,
                'event_id' => $eventIds['Summer Beats Festival'] ?? null,
                'offer_type' => 'event',
                'status' => 'active',
                'created_by' => $adminId,
            ],
            [
                'name' => 'Lunch Combo Deal',
                'details' => 'Flat $5 off any lunch combo this week.',
                'start_date' => $now->copy()->addDays(2),
                'end_date' => $now->copy()->addDays(9),
                'discount_type' => 'flat',
                'discount_value' => 5,
                'thumbnail' => null,
                'cover' => '/uploads/offers/sample-food.jpg',
                'images' => ['/uploads/offers/sample-food.jpg'],
                'videos' => [],
                'category_id' => $categoryIds['Food'] ?? null,
                'organization_id' => $orgId,
                'event_id' => null,
                'offer_type' => 'category',
                'status' => 'active',
                'created_by' => $adminId,
            ],
        ];

        foreach ($offers as $offer) {
            Offer::updateOrCreate(
                ['name' => $offer['name']],
                $offer
            );
        }
    }
}
