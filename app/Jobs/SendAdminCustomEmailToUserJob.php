<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Notifications\AdminCustomEmailNotification;
use App\Notifications\AdminCustomInAppNotification;
use App\Services\AdminEmail\AdminCustomEmailService;
use App\Support\AdminEmail\AdminEmailPlaceholderReplacer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;

final class SendAdminCustomEmailToUserJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $recipientUserId,
        public readonly string $subjectTemplate,
        public readonly string $bodyHtmlTemplate,
        public readonly bool $bypassEligibility = false,
        public readonly bool $sendAsNotification = false,
    ) {}

    public function uniqueId(): string
    {
        return implode(':', [
            (string) $this->recipientUserId,
            md5($this->subjectTemplate.$this->bodyHtmlTemplate),
        ]);
    }

    public int $uniqueFor = 300;

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        $middleware = [
            (new WithoutOverlapping($this->uniqueId()))->dontRelease(),
        ];

        if (! $this->sendAsNotification) {
            array_unshift($middleware, new RateLimited('admin-custom-email'));
        }

        return $middleware;
    }

    public function handle(
        AdminCustomEmailService $adminEmail,
        AdminEmailPlaceholderReplacer $placeholders,
    ): void {
        $user = User::query()->with('organization')->find($this->recipientUserId);

        if ($user === null) {
            return;
        }

        if (! $this->bypassEligibility && ! $adminEmail->isEligibleRecipient($user)) {
            return;
        }

        if ($this->bypassEligibility && trim($user->email) === '') {
            return;
        }

        $subject = $placeholders->replaceSubject($this->subjectTemplate, $user);
        $bodyHtml = $placeholders->replaceBodyHtml($this->bodyHtmlTemplate, $user);

        if ($this->sendAsNotification) {
            $user->notify(
                (new AdminCustomInAppNotification($subject, $bodyHtml))
                    ->locale($user->preferredLocale()),
            );

            return;
        }

        $user->notify(
            (new AdminCustomEmailNotification($subject, $bodyHtml))
                ->locale($user->preferredLocale()),
        );
    }
}
