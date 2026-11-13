<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WebsiteCorsTest extends TestCase
{
    public function test_both_website_domains_can_read_api_responses(): void
    {
        Route::get('/api/cors-check', fn () => response()->json(['success' => true]));
        foreach (['https://oworldbd.com', 'https://www.oworldbd.com'] as $origin) {
            $this->withHeaders(['Origin' => $origin])->getJson('/api/cors-check')
                ->assertOk()->assertHeader('Access-Control-Allow-Origin', $origin)
                ->assertHeader('Access-Control-Allow-Credentials', 'true');
            $this->withHeaders(['Origin' => $origin, 'Access-Control-Request-Method' => 'GET',
                'Access-Control-Request-Headers' => 'authorization,content-type'])
                ->options('/api/cors-check')->assertSuccessful()
                ->assertHeader('Access-Control-Allow-Origin', $origin);
        }
    }

    public function test_untrusted_origins_are_not_allowed(): void
    {
        Route::get('/api/cors-check', fn () => response()->json(['success' => true]));
        $this->withHeaders(['Origin' => 'https://untrusted.example'])->getJson('/api/cors-check')
            ->assertOk()->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
