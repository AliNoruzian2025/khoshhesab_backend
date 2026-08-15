<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UploadReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_upload_a_receipt_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['phone' => '09124444444']);
        Sanctum::actingAs($user);
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        );

        $response = $this->post('/api/uploads/receipt', [
            'receipt' => UploadedFile::fake()->createWithContent('receipt.png', $png),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonStructure(['message', 'path']);
        $storedPath = str_replace('storage/', '', $response->json('path'));
        Storage::disk('public')->assertExists($storedPath);
    }

    public function test_authenticated_user_can_upload_a_group_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['phone' => '09124444445']);
        Sanctum::actingAs($user);
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        );

        $response = $this->post('/api/uploads/group-image', [
            'image' => UploadedFile::fake()->createWithContent('group.png', $png),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonStructure(['message', 'path']);
        Storage::disk('public')->assertExists(str_replace('storage/', '', $response->json('path')));
    }
}
