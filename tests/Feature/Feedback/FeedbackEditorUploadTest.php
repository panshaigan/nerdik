<?php

declare(strict_types=1);

namespace Tests\Feature\Feedback;

use App\Enums\FeedbackType;
use App\Models\Feedback;
use App\Models\FeedbackUpload;
use App\Models\User;
use App\Services\Feedback\FeedbackSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FeedbackEditorUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Notification::fake();
    }

    public function test_guest_upload_is_private_and_only_visible_to_its_session(): void
    {
        $response = $this->post(route('feedback.editor-upload'), [
            'file' => UploadedFile::fake()->image('screenshot.png'),
        ])->assertOk()->assertJsonStructure(['location']);

        $upload = FeedbackUpload::query()->sole();
        Storage::disk('local')->assertExists($upload->path);
        $this->assertEmpty(Storage::disk('public')->allFiles());
        $this->get($response->json('location'))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        session()->forget('feedback_upload_owner');
        $this->get($response->json('location'))->assertNotFound();

        $this->actingAs(User::factory()->admin()->create())
            ->get($response->json('location'))->assertNotFound();
    }

    public function test_upload_rejects_non_image_and_oversized_image(): void
    {
        $this->post(route('feedback.editor-upload'), [
            'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('file');

        $this->post(route('feedback.editor-upload'), [
            'file' => UploadedFile::fake()->image('large.png')->size(2049),
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('feedback_uploads', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_session_and_global_quotas_reject_uploads_before_writing_files(): void
    {
        config(['feedback.upload_session_bytes' => 1]);
        $this->postJson(route('feedback.editor-upload'), [
            'file' => UploadedFile::fake()->image('screenshot.png'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');

        config(['feedback.upload_session_bytes' => 10485760, 'feedback.upload_pending_bytes' => 1]);
        $this->postJson(route('feedback.editor-upload'), [
            'file' => UploadedFile::fake()->image('screenshot.png'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('feedback_uploads', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_daily_ip_quota_survives_new_sessions_and_submission(): void
    {
        $this->post(route('feedback.editor-upload'), [
            'file' => UploadedFile::fake()->image('first.png'),
        ])->assertOk();

        $upload = FeedbackUpload::query()->sole();
        $upload->update(['feedback_id' => Feedback::factory()->create()->id, 'expires_at' => null]);
        config(['feedback.upload_daily_ip_bytes' => $upload->bytes]);
        session()->forget('feedback_upload_owner');

        $this->postJson(route('feedback.editor-upload'), [
            'file' => UploadedFile::fake()->image('second.png'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('feedback_uploads', 1);
    }

    public function test_submission_retains_only_referenced_images_and_allows_admin_access(): void
    {
        $location = $this->post(route('feedback.editor-upload'), [
            'file' => UploadedFile::fake()->image('attached.png'),
        ])->assertOk()->json('location');
        $attached = FeedbackUpload::query()->sole();

        $this->post(route('feedback.editor-upload'), [
            'file' => UploadedFile::fake()->image('abandoned.png'),
        ])->assertOk();
        $abandoned = FeedbackUpload::query()->whereKeyNot($attached->id)->sole();

        $feedback = app(FeedbackSubmissionService::class)->submit([
            'type' => FeedbackType::Bug->value,
            'subject' => 'Screenshot',
            'body' => '<p>Problem</p><img src="'.$location.'">',
            'email' => 'reporter@example.test',
        ]);

        $this->assertSame($feedback->id, $attached->fresh()->feedback_id);
        $this->assertNull($attached->fresh()->expires_at);
        $this->assertNull($abandoned->fresh()->feedback_id);

        session()->forget('feedback_upload_owner');
        $this->actingAs(User::factory()->create())->get($location)->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->get($location)->assertOk();

        $this->travel(25)->hours();
        $this->artisan('feedback:prune-uploads')->assertSuccessful();
        Storage::disk('local')->assertExists($attached->path);
        Storage::disk('local')->assertMissing($abandoned->path);
        $this->assertModelMissing($abandoned);
    }

    public function test_submission_cannot_claim_another_sessions_image(): void
    {
        $location = $this->post(route('feedback.editor-upload'), [
            'file' => UploadedFile::fake()->image('private.png'),
        ])->assertOk()->json('location');
        session()->forget('feedback_upload_owner');

        try {
            app(FeedbackSubmissionService::class)->submit([
                'type' => FeedbackType::Bug->value,
                'subject' => '',
                'body' => '<p>Problem</p><img src="'.$location.'">',
                'email' => 'other@example.test',
            ]);
            $this->fail('Another session claimed the upload.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('body', $exception->errors());
        }

        $this->assertDatabaseCount('feedback', 0);
        $this->assertNull(FeedbackUpload::query()->sole()->feedback_id);
    }

    public function test_expired_image_is_inaccessible_and_pruned(): void
    {
        $location = $this->post(route('feedback.editor-upload'), [
            'file' => UploadedFile::fake()->image('private.png'),
        ])->assertOk()->json('location');
        $upload = FeedbackUpload::query()->sole();
        $this->travel(25)->hours();

        $this->get($location)->assertNotFound();
        $this->artisan('feedback:prune-uploads')->assertSuccessful();
        Storage::disk('local')->assertMissing($upload->path);
        $this->assertModelMissing($upload);
    }

    public function test_legacy_migration_previews_then_preserves_referenced_files_privately(): void
    {
        Storage::disk('public')->put('feedback/editor/old.png', 'image');
        Storage::disk('public')->put('feedback/editor/abandoned.png', 'unused');
        $feedback = Feedback::factory()->create([
            'body' => '<p>Bug</p><img src="https://example.test/storage/feedback/editor/old.png">',
        ]);
        $this->artisan('feedback:migrate-uploads')->assertSuccessful();
        Storage::disk('public')->assertExists('feedback/editor/old.png');
        $this->assertDatabaseCount('feedback_uploads', 0);

        $this->artisan('feedback:migrate-uploads', ['--apply' => true, '--delete-unreferenced' => true])
            ->assertSuccessful();
        $upload = FeedbackUpload::query()->sole();
        $this->assertSame($feedback->id, $upload->feedback_id);
        $this->assertStringContainsString(route('feedback.editor-images.show', $upload), $feedback->fresh()->body);
        Storage::disk('local')->assertExists($upload->path);
        Storage::disk('public')->assertMissing('feedback/editor/old.png');
        Storage::disk('public')->assertMissing('feedback/editor/abandoned.png');
    }
}
