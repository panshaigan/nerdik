<?php

declare(strict_types=1);

namespace App\Support\AdminEmail;

use App\Models\User;

final class AdminEmailPlaceholderReplacer
{
    /** @var list<string> */
    public const PLACEHOLDERS = [
        '{name}',
        '{nickname}',
        '{email}',
        '{organization}',
    ];

    public function replaceSubject(string $template, User $user): string
    {
        return str_replace(
            self::PLACEHOLDERS,
            $this->subjectValues($user),
            $template,
        );
    }

    public function replaceBodyHtml(string $template, User $user): string
    {
        return str_replace(
            self::PLACEHOLDERS,
            $this->escapedHtmlValues($user),
            $template,
        );
    }

    /**
     * @return list<string>
     */
    private function subjectValues(User $user): array
    {
        $user->loadMissing('organization');

        return [
            $user->name ?? '',
            $user->nickname ?? '',
            $user->email ?? '',
            $user->organization?->name ?? '',
        ];
    }

    /**
     * @return list<string>
     */
    private function escapedHtmlValues(User $user): array
    {
        return array_map(
            e(...),
            $this->subjectValues($user),
        );
    }
}
