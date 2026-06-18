<?php

declare(strict_types=1);

namespace App\Services\UserRequests;

use App\Enums\UserRequestType;
use App\Models\Activity;
use App\Models\UserRequest;
use App\Services\EventActivitySignupService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class UserRequestExpirationResolver
{
    public function __construct(
        private readonly EventActivitySignupService $signupService,
    ) {}

    public function resolve(UserRequestType $type, ?Model $subject, ?CarbonInterface $now = null): Carbon
    {
        $now = $now ?? now();

        if ($type === UserRequestType::ActivityInvite && $subject instanceof Activity) {
            $window = $this->signupService->activityScheduledWindow($subject);
            if ($window !== null) {
                $start = $window[0];
                if ($start->isFuture()) {
                    return $start->copy();
                }
            }
        }

        $days = (int) config('user_requests.default_expiration_days', 14);

        return $now->copy()->addDays($days);
    }

    public function forRequest(UserRequest $request, ?CarbonInterface $now = null): Carbon
    {
        $subject = $request->subject;

        return $this->resolve($request->type, $subject, $now);
    }
}
