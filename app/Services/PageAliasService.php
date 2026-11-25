<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRequestType;
use App\Models\PageAlias;
use App\Models\User;
use App\Models\UserRequest;
use App\Services\UserRequests\UserRequestService;
use App\Support\PageAliases\PageAliasRules;
use Illuminate\Validation\ValidationException;

class PageAliasService
{
    public function create(User $actor, string $alias, string $targetPath, ?int $ignorePendingRequestId = null): PageAlias
    {
        $normalizedAlias = PageAliasRules::normalizeAlias($alias);
        $normalizedTarget = PageAliasRules::normalizeTargetPath($targetPath);

        $this->assertAliasAvailable($normalizedAlias, $ignorePendingRequestId);

        if (PageAliasRules::isReserved($normalizedAlias)) {
            throw ValidationException::withMessages([
                'alias' => [__('ui.page_aliases.alias_reserved')],
            ]);
        }

        return PageAlias::query()->create([
            'alias' => $normalizedAlias,
            'target_path' => $normalizedTarget,
            'created_by_id' => $actor->id,
        ]);
    }

    public function request(User $requester, string $alias, string $targetPath): UserRequest
    {
        $normalizedAlias = PageAliasRules::normalizeAlias($alias);
        $normalizedTarget = PageAliasRules::normalizeTargetPath($targetPath);

        $this->assertAliasAvailable($normalizedAlias);

        if (PageAliasRules::isReserved($normalizedAlias)) {
            throw ValidationException::withMessages([
                'alias' => [__('ui.page_aliases.alias_reserved')],
            ]);
        }

        return app(UserRequestService::class)->send(
            UserRequestType::PageAlias,
            $requester,
            null,
            null,
            null,
            [
                'alias' => $normalizedAlias,
                'target_path' => $normalizedTarget,
            ],
        );
    }

    public function resolve(string $alias): ?PageAlias
    {
        try {
            $normalized = PageAliasRules::normalizeAlias($alias);
        } catch (ValidationException) {
            return null;
        }

        return PageAlias::query()->where('alias', $normalized)->first();
    }

    public function assertAliasAvailable(string $normalizedAlias, ?int $ignorePendingRequestId = null): void
    {
        if (PageAlias::query()->where('alias', $normalizedAlias)->exists()) {
            throw ValidationException::withMessages([
                'alias' => [__('ui.page_aliases.alias_taken')],
            ]);
        }

        $pendingQuery = UserRequest::query()
            ->pending()
            ->where('type', UserRequestType::PageAlias)
            ->where('payload->alias', $normalizedAlias);

        if ($ignorePendingRequestId !== null) {
            $pendingQuery->whereKeyNot($ignorePendingRequestId);
        }

        $pendingExists = $pendingQuery->exists();

        if ($pendingExists) {
            throw ValidationException::withMessages([
                'alias' => [__('ui.page_aliases.alias_taken')],
            ]);
        }
    }

    /**
     * @param  int|null  $ignoreId  Existing PageAlias id when updating via Filament.
     */
    public function validateForFilament(string $alias, string $targetPath, ?int $ignoreId = null): array
    {
        $normalizedAlias = PageAliasRules::normalizeAlias($alias);
        $normalizedTarget = PageAliasRules::normalizeTargetPath($targetPath);

        if (PageAliasRules::isReserved($normalizedAlias)) {
            throw ValidationException::withMessages([
                'alias' => [__('ui.page_aliases.alias_reserved')],
            ]);
        }

        $aliasTaken = PageAlias::query()
            ->where('alias', $normalizedAlias)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($aliasTaken) {
            throw ValidationException::withMessages([
                'alias' => [__('ui.page_aliases.alias_taken')],
            ]);
        }

        if (! $ignoreId) {
            $pendingExists = UserRequest::query()
                ->pending()
                ->where('type', UserRequestType::PageAlias)
                ->where('payload->alias', $normalizedAlias)
                ->exists();

            if ($pendingExists) {
                throw ValidationException::withMessages([
                    'alias' => [__('ui.page_aliases.alias_taken')],
                ]);
            }
        }

        return [
            'alias' => $normalizedAlias,
            'target_path' => $normalizedTarget,
        ];
    }
}
