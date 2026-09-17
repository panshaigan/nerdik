<?php

declare(strict_types=1);

namespace Tests\Feature\Feedback;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FeedbackEditorUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_upload_image_for_editor(): void
    {
        Storage::fake('public');

        $response = $this->post(route('feedback.editor-upload'), [
            'file' => UploadedFile::fake()->image('screenshot.png', 800, 600),
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['location']);

        $location = (string) $response->json('location');
        $this->assertNotSame('', $location);
        $this->assertStringContainsString('feedback/editor', $location);
    }

    public function test_upload_rejects_non_image(): void
    {
        Storage::fake('public');

        $response = $this->post(route('feedback.editor-upload'), [
            'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ]);

        $response->assertSessionHasErrors('file');
    }
}
