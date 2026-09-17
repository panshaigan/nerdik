<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AppLocale;
use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feedback>
 */
final class FeedbackFactory extends Factory
{
    protected $model = Feedback::class;

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(FeedbackType::cases()),
            'subject' => fake()->sentence(6),
            'body' => '<p>'.e(fake()->paragraph()).'</p>',
            'email' => fake()->safeEmail(),
            'user_id' => null,
            'status' => FeedbackStatus::Open,
            'page_url' => fake()->url(),
            'locale' => AppLocale::En->value,
            'user_agent' => fake()->userAgent(),
        ];
    }

    public function fromUser(?User $user = null): static
    {
        return $this->state(function () use ($user): array {
            $user ??= User::factory()->create();

            return [
                'user_id' => $user->id,
                'email' => $user->email,
            ];
        });
    }

    public function guest(?string $email = null): static
    {
        return $this->state(fn (): array => [
            'user_id' => null,
            'email' => $email ?? fake()->safeEmail(),
        ]);
    }

    public function resolved(?User $resolver = null): static
    {
        return $this->state(function () use ($resolver): array {
            $resolver ??= User::factory()->admin()->create();

            return [
                'status' => FeedbackStatus::Resolved,
                'resolved_at' => now(),
                'resolved_by_id' => $resolver->id,
            ];
        });
    }

    public function replied(?User $replier = null, ?string $reply = null): static
    {
        return $this->state(function () use ($replier, $reply): array {
            $replier ??= User::factory()->admin()->create();

            return [
                'admin_reply' => $reply ?? fake()->paragraph(),
                'replied_at' => now(),
                'replied_by_id' => $replier->id,
                'status' => FeedbackStatus::Resolved,
                'resolved_at' => now(),
                'resolved_by_id' => $replier->id,
            ];
        });
    }
}
