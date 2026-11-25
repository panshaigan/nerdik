<?php

declare(strict_types=1);

namespace App\Services\UserRequests\Handlers;

use App\Contracts\UserRequests\UserRequestHandler;
use App\Enums\UserRequestResolutionOutcome;
use App\Enums\UserRequestType;
use App\Models\User;
use App\Models\UserRequest;
use App\Services\PageAliasService;
use App\Support\PageAliases\PageAliasRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class PageAliasRequestHandler implements UserRequestHandler
{
    public function __construct(
        private readonly PageAliasService $pageAliases,
    ) {}

    public function type(): UserRequestType
    {
        return UserRequestType::PageAlias;
    }

    public function assertCanSend(User $requester, ?User $recipient, ?Model $subject): void
    {
        if ($subject !== null) {
            throw ValidationException::withMessages([
                'subject' => [__('ui.page_aliases.request_invalid_subject')],
            ]);
        }

        if ($recipient !== null) {
            throw ValidationException::withMessages([
                'recipient' => [__('ui.page_aliases.request_invalid_recipient')],
            ]);
        }

        if ($requester->is_admin === true) {
            throw ValidationException::withMessages([
                'requester' => [__('ui.page_aliases.admin_use_create')],
            ]);
        }

        $maxPending = (int) config('page_aliases.max_pending_requests_per_user', 3);
        $pendingCount = UserRequest::query()
            ->pending()
            ->where('type', UserRequestType::PageAlias)
            ->where('requester_id', $requester->id)
            ->count();

        if ($pendingCount >= $maxPending) {
            throw ValidationException::withMessages([
                'requester' => [__('ui.page_aliases.pending_cap', ['max' => $maxPending])],
            ]);
        }
    }

    public function assertCanRespond(UserRequest $request, User $actor): void
    {
        if ($actor->is_admin !== true) {
            throw ValidationException::withMessages([
                'request' => [__('ui.user_requests.respond_unauthorized')],
            ]);
        }
    }

    public function apply(UserRequest $request): ?UserRequestResolutionOutcome
    {
        $requester = $request->requester;

        if ($requester === null) {
            throw ValidationException::withMessages([
                'request' => [__('ui.user_requests.invalid_request')],
            ]);
        }

        /** @var array<string, mixed>|null $payload */
        $payload = $request->payload;
        $alias = is_array($payload) ? ($payload['alias'] ?? null) : null;
        $targetPath = is_array($payload) ? ($payload['target_path'] ?? null) : null;

        if (! is_string($alias) || $alias === '' || ! is_string($targetPath) || $targetPath === '') {
            throw ValidationException::withMessages([
                'request' => [__('ui.page_aliases.invalid_request_payload')],
            ]);
        }

        try {
            PageAliasRules::normalizeAlias($alias);
            PageAliasRules::normalizeTargetPath($targetPath);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        $this->pageAliases->create($requester, $alias, $targetPath, $request->id);

        return null;
    }
}
