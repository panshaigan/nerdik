<?php

declare(strict_types=1);

namespace App\Services\AdminEmail;

use App\Jobs\SendAdminCustomEmailToUserJob;
use App\Models\User;
use App\Support\AdminEmail\AdminEmailRecipientCriteria;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class AdminCustomEmailService
{
    public function isEligibleRecipient(User $user): bool
    {
        if ($user->is_admin) {
            return false;
        }

        if ($user->email_verified_at === null) {
            return false;
        }

        return trim($user->email) !== '';
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function applyRecipientCriteria(Builder $query, AdminEmailRecipientCriteria $criteria): Builder
    {
        if ($criteria->verifiedOnly) {
            $query->whereNotNull('email_verified_at');
        }

        if ($criteria->excludeAdmins) {
            $query->where('is_admin', false);
        }

        if ($criteria->organizationId !== null) {
            $query->where('organization_id', $criteria->organizationId);
        }

        $query->where('email', '!=', '');

        return $query;
    }

    /**
     * @param  iterable<int|string>  $userIds
     */
    public function dispatchToRecipients(
        User $sender,
        string $subjectTemplate,
        string $bodyHtml,
        iterable $userIds,
    ): AdminEmailDispatchResult {
        $subject = trim($subjectTemplate);
        $sanitizedBody = RichText::sanitize($bodyHtml);

        if ($subject === '') {
            throw ValidationException::withMessages([
                'subject' => [__('admin_custom_email.validation.subject_required')],
            ]);
        }

        if ($sanitizedBody === null) {
            throw ValidationException::withMessages([
                'bodyHtml' => [__('admin_custom_email.validation.body_required')],
            ]);
        }

        $ids = Collection::make($userIds)
            ->map(fn (int|string $id): int => (int) $id)
            ->unique()
            ->values();

        $users = User::query()
            ->whereIn('id', $ids)
            ->get();

        $eligible = $users->filter(fn (User $user): bool => $this->isEligibleRecipient($user));
        $skippedIneligible = $ids->count() - $eligible->count();

        $maxRecipients = max(1, (int) config('admin_custom_email.max_recipients', 100));
        $toQueue = $eligible->take($maxRecipients);
        $skippedOverLimit = max(0, $eligible->count() - $toQueue->count());

        if ($toQueue->isEmpty()) {
            throw ValidationException::withMessages([
                'recipients' => [__('admin_custom_email.validation.no_eligible_recipients')],
            ]);
        }

        foreach ($toQueue as $recipient) {
            SendAdminCustomEmailToUserJob::dispatch(
                recipientUserId: (int) $recipient->getKey(),
                subjectTemplate: $subject,
                bodyHtmlTemplate: $sanitizedBody,
            );
        }

        return new AdminEmailDispatchResult(
            queued: $toQueue->count(),
            skippedIneligible: $skippedIneligible,
            skippedOverLimit: $skippedOverLimit,
        );
    }

    public function dispatchTestToSender(
        User $sender,
        string $subjectTemplate,
        string $bodyHtml,
    ): void {
        $subject = trim($subjectTemplate);
        $sanitizedBody = RichText::sanitize($bodyHtml);

        if ($subject === '') {
            throw ValidationException::withMessages([
                'subject' => [__('admin_custom_email.validation.subject_required')],
            ]);
        }

        if ($sanitizedBody === null) {
            throw ValidationException::withMessages([
                'bodyHtml' => [__('admin_custom_email.validation.body_required')],
            ]);
        }

        SendAdminCustomEmailToUserJob::dispatch(
            recipientUserId: (int) $sender->getKey(),
            subjectTemplate: $subject,
            bodyHtmlTemplate: $sanitizedBody,
            bypassEligibility: true,
        );
    }
}
