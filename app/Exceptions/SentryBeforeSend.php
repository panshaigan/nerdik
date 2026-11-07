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
     * Drop deploy/restart noise when the Compose Postgres service is briefly
     * unreachable (DNS flaps or connection refused to host "pgsql"). Real SQL
     * errors and failures against non-compose hosts are still reported.
     */
    public static function beforeSend(Event $event, ?EventHint $hint = null): ?Event
    {
        if (self::isTransientPgsqlConnectivityFailure($hint?->exception)) {
            return null;
        }

        return $event;
    }

    public static function isTransientPgsqlConnectivityFailure(?Throwable $throwable): bool
    {
        if (! $throwable instanceof QueryException) {
            return false;
        }

        $message = $throwable->getMessage();

        if (! str_contains($message, 'pgsql')) {
            return false;
        }

        return str_contains($message, 'could not translate host name "pgsql"')
            || str_contains($message, 'Temporary failure in name resolution')
            || str_contains($message, 'Connection refused');
    }
}
