<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\SentEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PruneSentEmailsCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_old_sent_emails_and_files(): void
    {
        Config::set('housekeeping.sent_email_retention_days', 90);
        Storage::fake('email_logs');

        $old = SentEmail::factory()->create([
            'sent_at' => now()->subDays(91),
            'html_path' => '2026/01/old.html',
            'text_path' => '2026/01/old.txt',
        ]);
        $fresh = SentEmail::factory()->create([
            'sent_at' => now()->subDays(10),
            'html_path' => '2026/08/fresh.html',
            'text_path' => '2026/08/fresh.txt',
        ]);

        Storage::disk('email_logs')->put('2026/01/old.html', '<p>old</p>');
        Storage::disk('email_logs')->put('2026/01/old.txt', 'old');
        Storage::disk('email_logs')->put('2026/08/fresh.html', '<p>fresh</p>');
        Storage::disk('email_logs')->put('2026/08/fresh.txt', 'fresh');

        $this->artisan('housekeeping:prune-sent-emails')
            ->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($fresh);
        Storage::disk('email_logs')->assertMissing('2026/01/old.html');
        Storage::disk('email_logs')->assertMissing('2026/01/old.txt');
        Storage::disk('email_logs')->assertExists('2026/08/fresh.html');
        Storage::disk('email_logs')->assertExists('2026/08/fresh.txt');
    }

    #[Test]
    public function dry_run_does_not_delete_old_sent_emails(): void
    {
        Config::set('housekeeping.sent_email_retention_days', 90);
        Storage::fake('email_logs');

        $old = SentEmail::factory()->create([
            'sent_at' => now()->subDays(91),
            'html_path' => '2026/01/old.html',
            'text_path' => null,
        ]);
        Storage::disk('email_logs')->put('2026/01/old.html', '<p>old</p>');

        $this->artisan('housekeeping:prune-sent-emails', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertModelExists($old);
        Storage::disk('email_logs')->assertExists('2026/01/old.html');
    }
}
