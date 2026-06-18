<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRequestStatus;
use App\Enums\UserRequestType;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserRequest>
 */
final class UserRequestFactory extends Factory
{
    protected $model = UserRequest::class;

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'type' => UserRequestType::OrganizationInvite,
            'status' => UserRequestStatus::Pending,
            'requester_id' => User::factory(),
            'recipient_id' => User::factory(),
            'subject_type' => 'organization',
            'subject_id' => Organization::factory(),
            'message' => fake()->optional()->sentence(),
            'expires_at' => now()->addDays(14),
        ];
    }

    public function organizationInvite(): self
    {
        return $this->state(fn (): array => [
            'type' => UserRequestType::OrganizationInvite,
        ]);
    }

    public function organizationJoinRequest(): self
    {
        return $this->state(fn (): array => [
            'type' => UserRequestType::OrganizationJoinRequest,
        ]);
    }

    public function activityInvite(): self
    {
        return $this->state(fn (): array => [
            'type' => UserRequestType::ActivityInvite,
        ]);
    }

    public function eventOrganizerFlag(): self
    {
        return $this->state(fn (): array => [
            'type' => UserRequestType::EventOrganizerFlag,
            'recipient_id' => null,
            'subject_type' => null,
            'subject_id' => null,
        ]);
    }
}
