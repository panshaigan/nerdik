<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Admin\Resources\SentEmails\Pages\ListSentEmails;
use App\Filament\Admin\Resources\SentEmails\Pages\ViewSentEmail;
use App\Filament\Admin\Resources\SentEmails\SentEmailResource;
use App\Models\SentEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

final class SentEmailResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('email_logs');
    }

    public function test_admin_can_list_sent_emails(): void
    {
        $admin = User::factory()->admin()->create();
        $email = SentEmail::factory()->create([
            'subject' => 'Waitlist promotion for Laser Tag',
            'recipient_email' => 'player@example.com',
        ]);

        Livewire::actingAs($admin)
            ->test(ListSentEmails::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$email]);
    }

    public function test_admin_can_view_sent_email(): void
    {
        $admin = User::factory()->admin()->create();
        $email = SentEmail::factory()->create([
            'subject' => 'Waitlist promotion for Laser Tag',
        ]);

        Livewire::actingAs($admin)
            ->test(ViewSentEmail::class, ['record' => $email->id])
            ->assertOk()
            ->assertSee('Waitlist promotion for Laser Tag');
    }

    public function test_admin_can_preview_stored_html(): void
    {
        $admin = User::factory()->admin()->create();
        $email = SentEmail::factory()->create();
        $path = now()->format('Y/m').'/'.$email->uuid.'.html';
        Storage::disk('email_logs')->put($path, '<p>Preview body</p>');
        $email->update(['html_path' => $path]);

        $this->actingAs($admin)
            ->get(route('filament.admin.sent-emails.preview', ['sentEmail' => $email]))
            ->assertOk()
            ->assertSee('Preview body', false)
            ->assertHeader('Content-Security-Policy');
    }

    public function test_non_admin_cannot_list_sent_emails(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(SentEmailResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_create_and_edit_pages_are_not_registered(): void
    {
        $this->assertArrayNotHasKey('create', SentEmailResource::getPages());
        $this->assertArrayNotHasKey('edit', SentEmailResource::getPages());
        $this->assertFalse(SentEmailResource::canCreate());
    }
}
