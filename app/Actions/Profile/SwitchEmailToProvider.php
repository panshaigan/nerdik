<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\User;
use App\Support\Profile\ProviderEmailOptions;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SwitchEmailToProvider
{
    public function __invoke(User $user, string $email): User
    {
        $normalizedEmail = strtolower($email);
        $availableEmails = ProviderEmailOptions::availableEmails($user);

        $validator = Validator::make(
            ['email' => $normalizedEmail],
            [
                'email' => ['required', 'string', 'email', 'max:255', Rule::in($availableEmails)],
            ],
            [
                'email.in' => __('ui.profile.use_provider_email_unavailable'),
            ],
        );

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                'selected_provider_email' => $validator->errors()->first('email'),
            ]);
        }

        if ($normalizedEmail === strtolower($user->email)) {
            throw ValidationException::withMessages([
                'selected_provider_email' => __('ui.profile.use_provider_email_same'),
            ]);
        }

        $uniquenessValidator = Validator::make(
            ['email' => $normalizedEmail],
            [
                'email' => [
                    'required',
                    'email',
                    Rule::unique(User::class, 'email')->ignore($user->id),
                    Rule::unique(User::class, 'pending_email'),
                ],
            ],
            [
                'email.unique' => __('ui.profile.use_provider_email_taken'),
            ],
        );

        if ($uniquenessValidator->fails()) {
            throw ValidationException::withMessages([
                'selected_provider_email' => __('ui.profile.use_provider_email_taken'),
            ]);
        }

        $wasVerified = $user->hasVerifiedEmail();

        $user->forceFill([
            'email' => $normalizedEmail,
            'pending_email' => null,
            'email_verified_at' => $user->freshTimestamp(),
        ])->save();

        if (! $wasVerified) {
            event(new Verified($user));
        }

        return $user->fresh();
    }
}
