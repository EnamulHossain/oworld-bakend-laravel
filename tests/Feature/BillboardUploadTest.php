<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AdminController;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BillboardUploadTest extends TestCase
{
    public function test_shared_billboard_upload_accepts_video_formats(): void
    {
        Storage::fake('public');
        foreach (['mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime', 'm4v' => 'video/x-m4v'] as $extension => $mime) {
            $request = Request::create('/api/admin/settings/upload', 'POST', [], [], [
                'image' => UploadedFile::fake()->create("billboard.$extension", 1024, $mime),
            ]);
            $response = app(AdminController::class)->uploadSettingImage($request);
            $this->assertSame(201, $response->getStatusCode());
            $url = $response->getData(true)['imageUrl'];
            Storage::disk('public')->assertExists(substr($url, strlen('/storage/')));
        }
    }

    public function test_video_over_twenty_megabytes_is_rejected(): void
    {
        $request = Request::create('/api/admin/settings/upload', 'POST', [], [], [
            'image' => UploadedFile::fake()->create('large.mp4', 20481, 'video/mp4'),
        ]);
        $this->expectException(ValidationException::class);
        app(AdminController::class)->uploadSettingImage($request);
    }
}
