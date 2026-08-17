<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SentEmailKind;
use App\Models\SentEmail;
use App\Models\User;
use App\Notifications\WaitlistPromotedNotification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SentEmail>
 */
final class SentEmailFactory extends Factory
{
    protected $model = SentEmail::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'sent_at' => now(),
            'kind' => SentEmailKind::WaitlistPromoted,
            'source_class' => WaitlistPromotedNotification::class,
            'subject' => fake()->sentence(),
            'from_email' => 'hello@example.com',
            'from_name' => 'Nerdik',
            'recipient_email' => fake()->unique()->safeEmail(),
            'recipient_user_id' => User::factory(),
            'cc' => [],
            'bcc' => [],
            'locale' => 'en',
            'mailer' => 'array',
            'provider_message_id' => null,
            'related_type' => null,
            'related_id' => null,
            'html_path' => null,
            'text_path' => null,
            'metadata' => [],
        ];
    }

    public function withoutRecipientUser(): self
    {
        return $this->state(fn (array $attributes): array => [
            'recipient_user_id' => null,
        ]);
    }
}
