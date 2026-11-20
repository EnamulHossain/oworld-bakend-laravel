<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AdminController;
use Illuminate\Http\{Request, UploadedFile};
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventImageUploadTest extends TestCase
{
    public function test_event_banner_accepts_an_image_and_returns_a_stored_url(): void
    {
        Storage::fake('public');
        $image = UploadedFile::fake()->createWithContent('event.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aY9sAAAAASUVORK5CYII='));
        $request = Request::create('/api/admin/events/upload-banner', 'POST', [], [], ['banner' => $image]);
        $response = app(AdminController::class)->uploadEventBanner($request);
        $this->assertSame(201, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertStringStartsWith('/storage/uploads/events/', $data['bannerUrl']);
        Storage::disk('public')->assertExists(substr($data['bannerUrl'], strlen('/storage/')));
    }

    public function test_event_banner_rejects_files_over_the_size_limit(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('large.jpg', 20481, 'image/jpeg');
        $request = Request::create('/api/admin/events/upload-banner', 'POST', [], [], ['banner' => $file]);
        $response = app(AdminController::class)->uploadEventBanner($request);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('20 MB', $response->getData(true)['message']);
    }
}
