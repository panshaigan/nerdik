<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use App\Enums\FeedbackStatus;
use App\Models\Feedback;
use App\Models\User;
use App\Notifications\FeedbackRepliedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

final class FeedbackReplyService
{
    public function reply(Feedback $feedback, User $admin, string $replyBody): Feedback
    {
        if ($feedback->hasReply()) {
            throw ValidationException::withMessages([
                'admin_reply' => [__('feedback.already_replied')],
            ]);
        }

        $reply = trim($replyBody);

        if ($reply === '') {
            throw ValidationException::withMessages([
                'admin_reply' => [__('validation.required', ['attribute' => 'reply'])],
            ]);
        }

        $feedback->forceFill([
            'admin_reply' => $reply,
            'replied_at' => now(),
            'replied_by_id' => $admin->id,
            'status' => FeedbackStatus::Resolved,
            'resolved_at' => now(),
            'resolved_by_id' => $admin->id,
        ])->save();

        $this->notifyReporter($feedback->fresh(['user']));

        return $feedback;
    }

    public function resolve(Feedback $feedback, User $admin): Feedback
    {
        $feedback->forceFill([
            'status' => FeedbackStatus::Resolved,
            'resolved_at' => now(),
            'resolved_by_id' => $admin->id,
        ])->save();

        return $feedback;
    }

    public function reopen(Feedback $feedback): Feedback
    {
        $feedback->forceFill([
            'status' => FeedbackStatus::Open,
            'resolved_at' => null,
            'resolved_by_id' => null,
        ])->save();

        return $feedback;
    }

    private function notifyReporter(Feedback $feedback): void
    {
        $notification = new FeedbackRepliedNotification($feedback);

        if ($feedback->user !== null) {
            $feedback->user->notify($notification);

            return;
        }

        $email = $feedback->reporterEmail();

        if ($email === null || $email === '') {
            return;
        }

        Notification::route('mail', $email)->notify($notification);
    }
}
