<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Database\QueryException;
use Sentry\Event;
use Sentry\EventHint;
use Throwable;

final class SentryBeforeSend
{
    /**
     * Drop deploy/restart noise when Docker DNS cannot resolve the Compose
     * Postgres service name. Real DB failures (refused, timeout, SQL errors)
     * are still reported.
     */
    public static function beforeSend(Event $event, ?EventHint $hint = null): ?Event
    {
        if (self::isTransientPgsqlDnsFailure($hint?->exception)) {
            return null;
        }

        return $event;
    }

    public static function isTransientPgsqlDnsFailure(?Throwable $throwable): bool
    {
        if (! $throwable instanceof QueryException) {
            return false;
        }

        $message = $throwable->getMessage();

        return str_contains($message, 'could not translate host name "pgsql"')
            || (
                str_contains($message, 'pgsql')
                && str_contains($message, 'Temporary failure in name resolution')
            );
    }
}
